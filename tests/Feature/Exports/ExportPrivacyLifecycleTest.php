<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Modules\Core\Exports\Application\Enums\ReportExportStatus;
use App\Modules\Core\Exports\Application\Lifecycle\ExportDataLifecycleParticipant;
use App\Modules\Core\Exports\Application\Public\Persistence\ExportsDatabaseTable;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\DTOs\FileLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExportPrivacyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_preview_reports_exports_and_blocks_active_requests(): void
    {
        $subject = new DataLifecycleSubject('person', '01JEXPORTPRIVACY00000001');
        $requestId = $this->insertRequest($subject->identifier, ReportExportStatus::Queued);
        $this->insertArtifact($requestId, $subject->identifier);
        $this->insertRenderCredential($requestId, $subject->identifier);

        $preview = $this->app->make(ExportDataLifecycleParticipant::class)
            ->preview($subject, DataLifecycleOperation::Delete);

        $dataSets = collect($preview->impacts)->map->dataSet->all();

        self::assertContains('exports.requests', $dataSets);
        self::assertContains('exports.artifacts', $dataSets);
        self::assertContains('exports.render_credentials', $dataSets);
        self::assertSame('export_generation_active', $preview->blockers[0]->code);
    }

    public function test_privacy_execution_redacts_completed_exports_and_removes_artifact_files(): void
    {
        $files = new RecordingExportFileLifecycle;
        $this->app->instance(FileLifecycle::class, $files);
        $subject = new DataLifecycleSubject('person', '01JEXPORTPRIVACY00000002');
        $requestId = $this->insertRequest($subject->identifier, ReportExportStatus::Available);
        $artifactPublicId = $this->insertArtifact($requestId, $subject->identifier);
        $this->insertRenderCredential($requestId, $subject->identifier);

        $result = $this->app->make(ExportDataLifecycleParticipant::class)
            ->execute($subject, DataLifecycleOperation::Delete, (string) Str::uuid());

        self::assertTrue($result->completed());
        self::assertSame([$subject->identifier], $files->deleted);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'id' => $requestId,
            'filters' => '{"privacy":"redacted"}',
            'authorization_snapshot' => '{"privacy":"redacted"}',
            'status' => ReportExportStatus::Expired->value,
            'safe_error_summary' => null,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'public_id' => $artifactPublicId,
            'status' => ReportExportStatus::Expired->value,
            'file_object_id' => null,
            'file_object_public_id' => null,
            'filename' => 'privacy-redacted-export-artifact',
            'size_bytes' => 0,
            'checksum_sha256' => null,
        ]);
        $this->assertDatabaseMissing(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS, [
            'export_request_id' => $requestId,
        ]);
    }

    private function insertRequest(string $subjectIdentifier, ReportExportStatus $status): int
    {
        return (int) DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'report_key' => 'privacy.export.test',
            'report_name' => 'Privacy export test',
            'module_key' => 'exports',
            'format' => 'csv',
            'team_id' => null,
            'active_team_public_id' => null,
            'requested_by_user_id' => null,
            'requesting_user_public_id' => $subjectIdentifier,
            'filters' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'sorting' => json_encode([], JSON_THROW_ON_ERROR),
            'visible_columns' => json_encode(['subject'], JSON_THROW_ON_ERROR),
            'column_order' => json_encode(['subject'], JSON_THROW_ON_ERROR),
            'time_range' => null,
            'authorization_snapshot' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'authorization_fingerprint' => hash('sha256', 'auth-'.$subjectIdentifier),
            'request_fingerprint' => hash('sha256', 'request-'.$subjectIdentifier.'-'.$status->value),
            'release_version' => 'test',
            'rule_version' => 'test-v1',
            'status' => $status->value,
            'synchronous_allowed' => true,
            'audit_export' => false,
            'estimated_row_count' => 1,
            'process_run_id' => null,
            'queued_at' => $status === ReportExportStatus::Queued ? now('UTC') : null,
            'started_at' => null,
            'finished_at' => $status === ReportExportStatus::Available ? now('UTC') : null,
            'failed_at' => null,
            'safe_error_summary' => 'Export mentions '.$subjectIdentifier,
            'expires_at' => now('UTC')->addDay(),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function insertArtifact(int $requestId, string $subjectIdentifier): string
    {
        $publicId = (string) Str::ulid();

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->insert([
            'public_id' => $publicId,
            'export_request_id' => $requestId,
            'file_object_id' => null,
            'file_object_public_id' => $subjectIdentifier,
            'status' => ReportExportStatus::Failed->value,
            'filename' => 'export-'.$subjectIdentifier.'.csv',
            'content_type' => 'text/csv',
            'size_bytes' => 120,
            'checksum_sha256' => str_repeat('a', 64),
            'created_by_user_id' => null,
            'available_at' => null,
            'failed_at' => now('UTC'),
            'expires_at' => now('UTC')->addDay(),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $publicId;
    }

    private function insertRenderCredential(int $requestId, string $subjectIdentifier): void
    {
        DB::table(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS)->insert([
            'public_id' => (string) Str::ulid(),
            'export_request_id' => $requestId,
            'token_hash' => hash('sha256', 'token-'.$subjectIdentifier),
            'requested_by_user_id' => null,
            'team_id' => null,
            'module_key' => 'exports',
            'report_key' => 'privacy.export.test',
            'allowed_dataset' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'allowed_columns' => json_encode(['subject'], JSON_THROW_ON_ERROR),
            'expires_at' => now('UTC')->addMinutes(5),
            'consumed_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}

final class RecordingExportFileLifecycle implements FileLifecycle
{
    /** @var list<string> */
    public array $deleted = [];

    /** @var list<string> */
    public array $anonymized = [];

    public function replace(string $publicId, UploadedFile $replacement, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        return new FileLifecycleResult($publicId, 'replace', true);
    }

    public function delete(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $this->deleted[] = $publicId;

        return new FileLifecycleResult($publicId, 'delete', true);
    }

    public function anonymize(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $this->anonymized[] = $publicId;

        return new FileLifecycleResult($publicId, 'anonymize', true);
    }

    public function createRetentionCopy(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        return new FileLifecycleResult($publicId, 'retention_copy', true);
    }

    public function createRetentionExport(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        return new FileLifecycleResult($publicId, 'retention_export', true);
    }
}
