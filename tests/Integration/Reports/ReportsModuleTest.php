<?php

declare(strict_types=1);

namespace Tests\Integration\Reports;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\Reports\Application\DTOs\AuthorizationFingerprint;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Enums\ReportExportFormat;
use App\Modules\Optional\Reports\Application\Enums\ReportExportStatus;
use App\Modules\Optional\Reports\Application\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Optional\Reports\Application\ReportExportGenerationProcess;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReportsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_immutable_authorized_export_request_snapshots_idempotently(): void
    {
        [$user, $team] = $this->userAndTeam();
        $snapshot = $this->snapshot($user, $team);

        $first = $this->app->make(ReportExportRequestRecorder::class)->record($snapshot);
        $second = $this->app->make(ReportExportRequestRecorder::class)->record($snapshot);

        self::assertSame($first->publicId, $second->publicId);
        self::assertSame(ReportExportStatus::Requested->value, $first->status);
        self::assertSame($snapshot->requestFingerprint(), $first->requestFingerprint);
        self::assertSame($snapshot->authorization->hash(), $first->authorizationFingerprint);
        $this->assertDatabaseCount(DatabaseTable::REPORT_EXPORT_REQUESTS, 1);
        $this->assertDatabaseHas(DatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $first->publicId,
            'report_key' => 'admin.users',
            'module_key' => 'users',
            'format' => ReportExportFormat::Csv->value,
            'status' => ReportExportStatus::Requested->value,
            'request_fingerprint' => $snapshot->requestFingerprint(),
            'authorization_fingerprint' => $snapshot->authorization->hash(),
        ]);
    }

    public function test_it_registers_generation_process_and_permission_module_mapping(): void
    {
        $definition = $this->app->make(ProcessDefinitionRegistry::class)->get(ReportExportGenerationProcess::KEY);
        $resolver = new ModuleKeyResolver;

        if ($definition === null) {
            self::fail('Report export generation process definition was not registered.');
        }

        self::assertSame('reports', $definition->moduleKey);
        self::assertSame('reports', $definition->queueName);
        self::assertSame('one_active_per_actor', $definition->concurrencyPolicy);
        self::assertFalse($definition->manualStartSupported);
        self::assertSame(ReportsPermissionCatalog::REQUEST, $definition->permissions->run);
        self::assertSame('reports', $resolver->forPermission(ReportsPermissionCatalog::ADMIN_INDEX));
        self::assertSame('reports', $resolver->forPermission(ReportsPermissionCatalog::REQUEST));
    }

    public function test_it_dispatches_generation_through_managed_process_runs(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);

        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $processRunId = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('public_id', $runPublicId)->value('id');

        $this->assertDatabaseHas(DatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Queued->value,
            'process_run_id' => $processRunId,
        ]);
    }

    public function test_available_artifacts_must_be_complete_before_they_can_be_downloadable(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');

        $this->expectException(QueryException::class);

        DB::table(DatabaseTable::REPORT_EXPORT_ARTIFACTS)->insert([
            'public_id' => (string) Str::ulid(),
            'export_request_id' => $requestId,
            'file_object_id' => null,
            'status' => ReportExportStatus::Available->value,
            'filename' => 'admin-users.csv',
            'content_type' => 'text/csv',
            'size_bytes' => 10,
            'checksum_sha256' => str_repeat('a', 64),
            'created_by_user_id' => $user->id,
            'available_at' => now(),
            'failed_at' => null,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_only_one_available_artifact_can_exist_per_export_request(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');

        $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');

        $this->expectException(QueryException::class);

        $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users-copy.csv');
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userAndTeam(): array
    {
        $user = User::factory()->create(['public_id' => '01J00000000000000000000061']);
        $team = Team::query()->create([
            'public_id' => '01J00000000000000000000062',
            'name' => 'Operations',
            'slug' => 'operations-reports',
            'is_active' => true,
        ]);

        return [$user, $team];
    }

    private function snapshot(User $user, Team $team): ReportExportRequestSnapshot
    {
        return new ReportExportRequestSnapshot(
            reportKey: 'admin.users',
            reportName: 'Admin users',
            moduleKey: 'users',
            format: ReportExportFormat::Csv,
            activeTeamId: (int) $team->id,
            activeTeamPublicId: (string) $team->public_id,
            requestingUserId: (int) $user->id,
            requestingUserPublicId: (string) $user->public_id,
            filters: ['search' => 'anna'],
            sorting: [['id' => 'email', 'desc' => false]],
            visibleColumns: ['public_id', 'email', 'status'],
            columnOrder: ['public_id', 'email', 'status'],
            timeRange: ['from' => null, 'to' => null],
            authorization: new AuthorizationFingerprint(
                moduleKey: 'users',
                activeTeamPublicId: (string) $team->public_id,
                requestingUserPublicId: (string) $user->public_id,
                permissionNames: [ReportsPermissionCatalog::REQUEST, 'admin.users.index'],
                allowedColumns: ['email', 'public_id', 'status'],
                ruleVersion: 'reports-v1',
            ),
            releaseVersion: 'test-release',
            ruleVersion: 'reports-v1',
            expiresAt: new DateTimeImmutable('+7 days'),
            synchronousAllowed: true,
        );
    }

    private function insertAvailableArtifact(int $requestId, int $userId, string $filename): void
    {
        $fileObjectId = DB::table(DatabaseTable::FILE_OBJECTS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'disk' => 'local',
            'path' => 'reports/'.$filename,
            'canonical_file_object_id' => null,
            'retention_source_file_object_id' => null,
            'physical_owner' => true,
            'original_name' => $filename,
            'extension' => 'csv',
            'mime_type' => 'text/csv',
            'size_bytes' => 10,
            'checksum_sha256' => str_repeat('b', 64),
            'scan_state' => 'clean',
            'scan_state_changed_at' => now(),
            'scan_attempts' => 1,
            'last_scan_queued_at' => null,
            'available_at' => now(),
            'quarantined_at' => now(),
            'anonymized_at' => null,
            'deleted_at' => null,
            'retention_purpose' => null,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::REPORT_EXPORT_ARTIFACTS)->insert([
            'public_id' => (string) Str::ulid(),
            'export_request_id' => $requestId,
            'file_object_id' => $fileObjectId,
            'status' => ReportExportStatus::Available->value,
            'filename' => $filename,
            'content_type' => 'text/csv',
            'size_bytes' => 10,
            'checksum_sha256' => str_repeat('b', 64),
            'created_by_user_id' => $userId,
            'available_at' => now(),
            'failed_at' => null,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function numericId(mixed $value): int
    {
        if (! is_numeric($value)) {
            self::fail('Expected a numeric database identifier.');
        }

        return (int) $value;
    }
}

final class FakeReportManagedProcessRunner implements ManagedProcessRunner
{
    public function start(
        string $processKey,
        string $sourceType,
        ?array $input,
        ?string $actorPublicId,
        ?string $teamPublicId,
        ?string $causationId = null,
    ): string {
        $publicId = (string) Str::ulid();
        $actorId = DB::table(DatabaseTable::USERS)->where('public_id', $actorPublicId)->value('id');
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->insert([
            'public_id' => $publicId,
            'process_key' => $processKey,
            'module_key' => 'reports',
            'scope' => 'team',
            'team_id' => is_numeric($teamId) ? (int) $teamId : null,
            'actor_user_id' => is_numeric($actorId) ? (int) $actorId : null,
            'source_type' => $sourceType,
            'input_snapshot' => json_encode($input ?? ['_input' => 'none'], JSON_THROW_ON_ERROR),
            'queue_connection' => 'sync',
            'queue_name' => 'reports',
            'job_identifier' => null,
            'status' => ProcessRunStatus::Queued->value,
            'current_stage' => 'queued',
            'progress_current' => 0,
            'progress_total' => null,
            'progress_label' => 'Queued',
            'counters' => json_encode(['processed' => 0, 'success' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'failed' => 0, 'skipped' => 0, 'retried' => 0], JSON_THROW_ON_ERROR),
            'correlation_id' => (string) Str::uuid(),
            'causation_id' => $causationId,
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }

    public function retry(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): string
    {
        return $runPublicId;
    }

    public function cancel(string $runPublicId, ?string $actorPublicId, ?string $teamPublicId, string $reason): void {}

    public function log(string $runPublicId, ProcessLogEntry $entry): void {}

    public function updateProgress(
        string $runPublicId,
        ProcessRunStatus $status,
        ?string $stage = null,
        ?int $current = null,
        ?int $total = null,
        ?string $label = null,
        ?array $counters = null,
        ?array $resultSummary = null,
        ?string $safeErrorSummary = null,
    ): void {}
}
