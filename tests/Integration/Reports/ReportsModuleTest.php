<?php

declare(strict_types=1);

namespace Tests\Integration\Reports;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionDecision;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Exports\Application\AdminDataTableExportProviderRegistry;
use App\Modules\Core\Exports\Application\AdminDataTableExportSnapshotFactory;
use App\Modules\Core\Exports\Application\Contracts\AdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportChartProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportExportDataProvider;
use App\Modules\Core\Exports\Application\Contracts\ReportRenderReadinessProbe;
use App\Modules\Core\Exports\Application\DTOs\AuthorizationFingerprint;
use App\Modules\Core\Exports\Application\DTOs\ReportChartDefinition;
use App\Modules\Core\Exports\Application\DTOs\ReportChartPoint;
use App\Modules\Core\Exports\Application\DTOs\ReportChartSeries;
use App\Modules\Core\Exports\Application\DTOs\ReportExportColumn;
use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\DTOs\ReportRenderReadinessResult;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Enums\ReportExportStatus;
use App\Modules\Core\Exports\Application\Exceptions\ReportArtifactNotDownloadable;
use App\Modules\Core\Exports\Application\Exceptions\ReportRenderCredentialInvalid;
use App\Modules\Core\Exports\Application\Exceptions\ReportRenderVisualsNotReady;
use App\Modules\Core\Exports\Application\ExportsDeactivationGuard;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportArtifactAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportGenerationDispatcher;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportMaintenance;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportRequestRecorder;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Core\Exports\Application\Public\DTOs\AdminDataTableExportContext;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Exports\Application\Public\Persistence\ExportsDatabaseTable;
use App\Modules\Core\Exports\Application\ReportExportDataProviderRegistry;
use App\Modules\Core\Exports\Application\ReportExportGenerationProcess;
use App\Modules\Core\Exports\Infrastructure\Runtime\ReportExportGenerationProcessHandler;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\AdminUserCredentialAccount;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Application\Exports\AdminUsersDataTableExportProvider;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\TableColumn;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableState;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ReportsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
    }

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
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, 1);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $first->publicId,
            'report_key' => 'admin.users',
            'module_key' => 'users',
            'format' => ReportExportFormat::Csv->value,
            'status' => ReportExportStatus::Requested->value,
            'request_fingerprint' => $snapshot->requestFingerprint(),
            'authorization_fingerprint' => $snapshot->authorization->hash(),
        ]);
    }

    public function test_audit_exports_are_fingerprinted_and_marked_separately(): void
    {
        [$user, $team] = $this->userAndTeam();
        $ordinary = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $audit = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, auditExport: true));

        self::assertNotSame($ordinary->publicId, $audit->publicId);
        self::assertNotSame($ordinary->requestFingerprint, $audit->requestFingerprint);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $audit->publicId,
            'audit_export' => true,
        ]);
    }

    public function test_synchronous_export_policy_requires_safe_estimates_and_keeps_pdfs_queued_by_default(): void
    {
        [$user, $team] = $this->userAndTeam();

        $withoutEstimate = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $smallCsv = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, estimatedRowCount: 2));
        $pdf = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Pdf, estimatedRowCount: 1));

        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $withoutEstimate->publicId,
            'synchronous_allowed' => false,
            'estimated_row_count' => null,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $smallCsv->publicId,
            'synchronous_allowed' => true,
            'estimated_row_count' => 2,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $pdf->publicId,
            'format' => ReportExportFormat::Pdf->value,
            'synchronous_allowed' => false,
            'estimated_row_count' => 1,
        ]);
    }

    public function test_recording_export_requests_rechecks_module_gate(): void
    {
        [$user, $team] = $this->userAndTeam();
        $this->app->bind(ModuleGate::class, DenyReportRequestModuleGate::class);

        $this->expectException(\RuntimeException::class);
        $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
    }

    public function test_it_registers_generation_process_and_permission_module_mapping(): void
    {
        $definition = $this->app->make(ProcessDefinitionRegistry::class)->get(ReportExportGenerationProcess::KEY);
        $resolver = new ModuleKeyResolver;

        if ($definition === null) {
            self::fail('Report export generation process definition was not registered.');
        }

        self::assertSame('exports', $definition->moduleKey);
        self::assertSame('exports', $definition->queueName);
        self::assertSame('one_active_per_actor', $definition->concurrencyPolicy);
        self::assertFalse($definition->manualStartSupported);
        self::assertSame(ReportsPermissionCatalog::REQUEST, $definition->permissions->run);
        self::assertSame('exports', $resolver->forPermission(ReportsPermissionCatalog::ADMIN_INDEX));
        self::assertSame('exports', $resolver->forPermission(ReportsPermissionCatalog::REQUEST));
    }

    public function test_admin_data_table_state_is_mapped_to_authorized_export_snapshots(): void
    {
        [$user, $team] = $this->userAndTeam();
        $provider = new FakeAdminUsersDataTableExportProvider;

        $snapshot = (new AdminDataTableExportSnapshotFactory)->snapshot(
            $provider,
            new AdminDataTableExportContext(
                state: new TableState(
                    page: 3,
                    perPage: 250,
                    sort: 'secret',
                    direction: 'desc',
                    search: " anna\n ",
                    columns: ['secret', 'email', 'unknown', 'publicId'],
                    columnOrder: ['secret', 'publicId', 'email'],
                    view: '01JVIEW',
                ),
                requestingUserId: (int) $user->id,
                requestingUserPublicId: (string) $user->public_id,
                activeTeamId: (int) $team->id,
                activeTeamPublicId: (string) $team->public_id,
                filters: ['status' => 'active'],
                timeRange: ['from' => '2026-07-01', 'to' => '2026-07-22'],
                estimatedRowCount: 12,
            ),
            ReportExportFormat::Xlsx,
        );

        self::assertSame('admin.users', $snapshot->reportKey);
        self::assertSame('Admin users', $snapshot->reportName);
        self::assertSame('users', $snapshot->moduleKey);
        self::assertSame(ReportExportFormat::Xlsx, $snapshot->format);
        self::assertSame(['status' => 'active', 'search' => 'anna'], $snapshot->filters);
        self::assertSame([['id' => 'email', 'desc' => true]], $snapshot->sorting);
        self::assertSame(['email', 'publicId'], $snapshot->visibleColumns);
        self::assertSame(['publicId', 'email'], $snapshot->columnOrder);
        self::assertSame(['from' => '2026-07-01', 'to' => '2026-07-22'], $snapshot->timeRange);
        self::assertSame(12, $snapshot->estimatedRowCount);

        $authorization = $snapshot->authorization->toArray();

        self::assertSame('users', $authorization['module_key']);
        self::assertSame([ReportsPermissionCatalog::REQUEST], $authorization['permission_names']);
        self::assertSame(['email', 'publicId'], $authorization['allowed_columns']);
        self::assertSame('admin-users-export-v1', $authorization['rule_version']);
    }

    public function test_admin_data_table_export_providers_are_available_to_admin_and_report_registries(): void
    {
        $this->app->bind('exports.test.admin_data_table_provider', fn (): FakeAdminUsersDataTableExportProvider => new FakeAdminUsersDataTableExportProvider);
        $this->app->tag(['exports.test.admin_data_table_provider'], 'atlas.admin_data_table_export_providers');

        $adminProvider = $this->app->make(AdminDataTableExportProviderRegistry::class)->get('admin.users');
        $reportProvider = $this->app->make(ReportExportDataProviderRegistry::class)->get('admin.users');

        self::assertInstanceOf(FakeAdminUsersDataTableExportProvider::class, $adminProvider);
        self::assertInstanceOf(FakeAdminUsersDataTableExportProvider::class, $reportProvider);
        self::assertSame($adminProvider->tableKey(), $reportProvider->reportKey());
        self::assertContains('admin.users', $this->app->make(AdminDataTableExportProviderRegistry::class)->tableKeys());
    }

    public function test_shared_admin_data_table_export_providers_are_registered(): void
    {
        $registry = $this->app->make(AdminDataTableExportProviderRegistry::class);
        $keys = $registry->tableKeys();

        foreach ([
            AdminTableDefinitions::USERS,
            AdminTableDefinitions::TEAMS,
            AdminTableDefinitions::MANAGER_RELATIONSHIP_HISTORY,
            AdminTableDefinitions::ROLES,
            AdminTableDefinitions::PACKAGES,
            AdminTableDefinitions::PERMISSIONS,
            AdminTableDefinitions::AUDIT,
            AdminTableDefinitions::SECURITY_HISTORY,
            AdminTableDefinitions::IMPERSONATION_SESSION_EVENTS,
            AdminTableDefinitions::RATE_LIMITS,
            AdminTableDefinitions::MODULES,
            AdminTableDefinitions::APPLICATION_LOGS,
            AdminTableDefinitions::FAILED_JOBS,
            AdminTableDefinitions::MODULE_DETAIL_TEAMS,
            AdminTableDefinitions::MODULE_DETAIL_HISTORY,
            AdminTableDefinitions::MODULE_DETAIL_SCHEDULES,
            AdminTableDefinitions::TIME_TRACKING_USER_REPORT,
            AdminTableDefinitions::TIME_TRACKING_MANAGER_REPORT,
            AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_DAILY,
            AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_OTHER_WORK,
            AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_WORK_SESSIONS,
            AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_BREAKS,
            AdminTableDefinitions::TIME_TRACKING_ADMIN_OPERATIONS_CORRECTIONS,
            AdminTableDefinitions::FILES,
            AdminTableDefinitions::INTEGRATION_ADAPTERS,
            AdminTableDefinitions::INTEGRATION_RUNS,
            AdminTableDefinitions::SEARCH_INDEXES,
            AdminTableDefinitions::SEARCH_REBUILDS,
            AdminTableDefinitions::FEATURE_FLAGS,
            AdminTableDefinitions::FEATURE_FLAG_HISTORY,
            AdminTableDefinitions::MANAGED_PROCESS_RUNS,
            AdminTableDefinitions::MANAGED_PROCESS_DEFINITIONS,
            AdminTableDefinitions::MANAGED_PROCESS_SCHEDULES,
            AdminTableDefinitions::IMPORT_ROW_ERRORS,
        ] as $tableKey) {
            self::assertContains($tableKey, $keys);

            $provider = $registry->get($tableKey);
            self::assertSame($tableKey, $provider->reportKey());
            self::assertContains(ReportExportFormat::Csv, $provider->supportedFormats(new AdminDataTableExportContext(
                state: TableState::fromPayload([], $provider->tableDefinition()),
                requestingUserId: 1,
                requestingUserPublicId: '01J000000000000000000000AA',
                activeTeamId: null,
                activeTeamPublicId: null,
                filters: [],
                timeRange: null,
                estimatedRowCount: null,
            )));
        }
    }

    public function test_admin_users_data_table_export_provider_maps_filters_sorting_and_safe_columns(): void
    {
        $provider = new AdminUsersDataTableExportProvider(new FakeUserCredentialAccountDirectory);
        $request = new ReportExportGenerationRequest(
            publicId: '01JEXPORT0000000000000001',
            reportKey: 'admin.users',
            reportName: 'Admin users',
            moduleKey: 'users',
            format: ReportExportFormat::Csv,
            activeTeamPublicId: null,
            requestingUserPublicId: '01J000000000000000000000AA',
            filters: ['search' => 'anna'],
            sorting: [['id' => 'email', 'desc' => true]],
            visibleColumns: ['publicId', 'email', 'failedLoginAttempts'],
            columnOrder: ['email', 'publicId', 'failedLoginAttempts'],
            allowedColumns: ['publicId', 'email', 'failedLoginAttempts'],
            timeRange: null,
            releaseVersion: 'test-release',
            ruleVersion: 'admin-users-export-v1',
            expiresAt: new DateTimeImmutable('+7 days'),
        );

        $rows = iterator_to_array($provider->rows($request), false);
        $columns = $provider->columns($request);

        self::assertSame(AdminTableDefinitions::USERS, $provider->tableKey());
        self::assertSame(ReportsPermissionCatalog::REQUEST, $provider->requestPermission());
        self::assertSame(AdminTableDefinitions::get(AdminTableDefinitions::USERS)->columnKeys(), $provider->allowedExportColumns(new AdminDataTableExportContext(
            state: TableState::fromPayload([], AdminTableDefinitions::get(AdminTableDefinitions::USERS)),
            requestingUserId: 1,
            requestingUserPublicId: '01J000000000000000000000AA',
            activeTeamId: null,
            activeTeamPublicId: null,
            filters: [],
            timeRange: null,
            estimatedRowCount: null,
        )));
        self::assertSame('Public ID', $columns[0]->label);
        self::assertCount(1, $rows);
        self::assertSame('anna@example.test', $rows[0]['email'] ?? null);
        self::assertSame('01J000000000000000000000AA', $rows[0]['publicId'] ?? null);
    }

    public function test_admin_data_table_export_endpoint_rebuilds_safe_authorized_snapshots(): void
    {
        [$user, $team] = $this->userAndTeam();
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);

        $response = $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => (string) $team->public_id,
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            ])
            ->post(route('admin.exports.data-table'), [
                'table_key' => AdminTableDefinitions::USERS,
                'format' => ReportExportFormat::Csv->value,
                'page' => 2,
                'per_page' => 250,
                'sort' => 'not_a_column',
                'direction' => 'desc',
                'search' => " anna\n",
                'columns' => 'email,not_a_column,publicId,email',
                'column_order' => 'not_a_column,publicId,email',
                'status' => 'active',
                'nested' => ['ignored' => true],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.messages.0.key', 'flash.exports.queued');
        $response->assertSessionMissing('success');

        $record = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('report_key', AdminTableDefinitions::USERS)->first();

        self::assertNotNull($record);
        self::assertSame('users', $record->module_key);
        self::assertSame((string) $team->public_id, $record->active_team_public_id);
        self::assertSame((string) $user->public_id, $record->requesting_user_public_id);
        self::assertSame(ReportExportStatus::Queued->value, $record->status);
        self::assertFalse((bool) $record->synchronous_allowed);
        self::assertSame(['search' => 'anna', 'status' => 'active'], $this->jsonObject($record->filters ?? null));
        self::assertSame([['id' => 'name', 'desc' => true]], $this->jsonObjectOrList($record->sorting ?? null));
        self::assertSame(['email', 'publicId'], $this->jsonStringList($record->visible_columns ?? null));
        self::assertSame(['publicId', 'email'], $this->jsonStringList($record->column_order ?? null));

        $authorization = $this->jsonObject($record->authorization_snapshot ?? null);

        self::assertSame('users', $authorization['module_key'] ?? null);
        self::assertSame((string) $team->public_id, $authorization['active_team_public_id'] ?? null);
        self::assertSame((string) $user->public_id, $authorization['requesting_user_public_id'] ?? null);
        self::assertSame([ReportsPermissionCatalog::REQUEST], $authorization['permission_names'] ?? null);
        self::assertSame('admin-users-export-v1', $authorization['rule_version'] ?? null);
        self::assertNotNull($record->process_run_id);
    }

    public function test_admin_data_table_export_endpoint_rechecks_owning_module_gate(): void
    {
        [$user, $team] = $this->userAndTeam();
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);
        $this->app->bind(ModuleGate::class, DenyUsersReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);

        $response = $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => (string) $team->public_id,
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            ])
            ->post(route('admin.exports.data-table'), [
                'table_key' => AdminTableDefinitions::USERS,
                'format' => ReportExportFormat::Csv->value,
                'columns' => 'email',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.messages.0.key', 'flash.exports.queue_failed');
        $response->assertSessionMissing('error');
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, 0);
    }

    public function test_admin_data_table_browser_print_endpoint_redirects_to_print_view(): void
    {
        [$user, $team] = $this->userAndTeam();
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);

        $response = $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => (string) $team->public_id,
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            ])
            ->post(route('admin.exports.data-table'), [
                'table_key' => AdminTableDefinitions::USERS,
                'format' => ReportExportFormat::BrowserPrint->value,
                'columns' => 'publicId,email',
            ]);

        $record = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('report_key', AdminTableDefinitions::USERS)->first();

        self::assertNotNull($record);
        self::assertSame(ReportExportStatus::Requested->value, $record->status);
        $response->assertRedirect(route('exports.print', ['export' => $this->stringValue($record->public_id ?? null)]));
    }

    public function test_admin_data_table_export_endpoint_runs_recorded_csv_request_through_real_handler(): void
    {
        [$user, $team] = $this->userAndTeam();
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        $response = $this->actingAs($user)
            ->withSession([
                'active_team_public_id' => (string) $team->public_id,
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            ])
            ->post(route('admin.exports.data-table'), [
                'table_key' => AdminTableDefinitions::USERS,
                'format' => ReportExportFormat::Csv->value,
                'columns' => 'publicId,email',
            ]);

        $response->assertRedirect();

        $runPublicId = DB::table(ManagedProcessesDatabaseTable::RUNS)
            ->where('process_key', ReportExportGenerationProcess::KEY)
            ->value('public_id');

        self::assertIsString($runPublicId);

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);

        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'report_key' => AdminTableDefinitions::USERS,
            'status' => ReportExportStatus::Available->value,
        ]);
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

        $processRunId = DB::table(ManagedProcessesDatabaseTable::RUNS)->where('public_id', $runPublicId)->value('id');

        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Queued->value,
            'process_run_id' => $processRunId,
        ]);
    }

    public function test_dispatch_snapshot_generates_small_exports_synchronously(): void
    {
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');
        [$user, $team] = $this->userAndTeam();

        $result = $this->app->make(ReportExportGenerationDispatcher::class)->dispatchSnapshot($this->snapshot(
            user: $user,
            team: $team,
            estimatedRowCount: 1,
        ));

        self::assertSame('sync', $result->executionMode);
        self::assertNotNull($result->artifactPublicId);
        self::assertNull($result->processRunPublicId);
        $this->assertDatabaseCount(ManagedProcessesDatabaseTable::RUNS, 0);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $result->exportRequestPublicId,
            'status' => ReportExportStatus::Available->value,
            'synchronous_allowed' => true,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'public_id' => $result->artifactPublicId,
            'status' => ReportExportStatus::Available->value,
        ]);
    }

    public function test_dispatch_snapshot_reuses_available_artifact_without_queuing_duplicate_process(): void
    {
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');
        [$user, $team] = $this->userAndTeam();
        $snapshot = $this->snapshot($user, $team, estimatedRowCount: 1);

        $first = $this->app->make(ReportExportGenerationDispatcher::class)->dispatchSnapshot($snapshot);
        $second = $this->app->make(ReportExportGenerationDispatcher::class)->dispatchSnapshot($snapshot);

        self::assertSame('sync', $first->executionMode);
        self::assertSame('cached', $second->executionMode);
        self::assertSame($first->exportRequestPublicId, $second->exportRequestPublicId);
        self::assertSame($first->artifactPublicId, $second->artifactPublicId);
        $this->assertDatabaseCount(ManagedProcessesDatabaseTable::RUNS, 0);
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, 1);
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, 1);
    }

    public function test_dispatch_snapshot_queues_pdfs_even_when_the_estimate_is_small_by_default(): void
    {
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        [$user, $team] = $this->userAndTeam();

        $result = $this->app->make(ReportExportGenerationDispatcher::class)->dispatchSnapshot($this->snapshot(
            user: $user,
            team: $team,
            format: ReportExportFormat::Pdf,
            estimatedRowCount: 1,
        ));

        self::assertSame('queued', $result->executionMode);
        self::assertNotNull($result->processRunPublicId);
        self::assertNull($result->artifactPublicId);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $result->exportRequestPublicId,
            'status' => ReportExportStatus::Queued->value,
            'synchronous_allowed' => false,
        ]);
    }

    public function test_report_requests_block_owning_module_deactivation_until_terminal(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $guard = $this->app->make(ExportsDeactivationGuard::class);

        $blocked = $guard->assess(new ModuleDeactivationRequest(
            moduleKey: new ModuleKey('users'),
            teamId: (int) $team->id,
            requestedBy: (string) $user->public_id,
        ));

        self::assertFalse($blocked->canDeactivate());
        self::assertSame('report_export', $blocked->blockers[0]->processType);
        self::assertSame($request->publicId, $blocked->blockers[0]->processIdentifier);

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->update([
            'status' => ReportExportStatus::Failed->value,
            'updated_at' => now(),
        ]);

        $allowed = $guard->assess(new ModuleDeactivationRequest(
            moduleKey: new ModuleKey('users'),
            teamId: (int) $team->id,
            requestedBy: (string) $user->public_id,
        ));

        self::assertTrue($allowed->canDeactivate());
    }

    public function test_available_artifacts_must_be_complete_before_they_can_be_downloadable(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');

        $this->expectException(QueryException::class);

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->insert([
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
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');

        $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');

        $this->expectException(QueryException::class);

        $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users-copy.csv');
    }

    public function test_download_reauthorizes_actor_team_module_and_clean_file(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $artifactPublicId = $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');

        $download = $this->app->make(ReportExportArtifactAccess::class)->download(
            artifactPublicId: $artifactPublicId,
            actorPublicId: (string) $user->public_id,
            activeTeamPublicId: (string) $team->public_id,
        );

        self::assertSame($artifactPublicId, $download->artifactPublicId);
        self::assertSame($request->publicId, $download->exportRequestPublicId);
        self::assertSame('local', $download->disk);
        self::assertSame('reports/admin-users.csv', $download->path);
        self::assertSame('admin-users.csv', $download->filename);
        self::assertSame('text/csv', $download->contentType);
        self::assertSame(10, $download->sizeBytes);
    }

    public function test_download_route_uses_route_permission_and_artifact_authorization(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $artifactPublicId = $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');

        $response = $this->actingAs($user)
            ->withSession(['active_team_public_id' => (string) $team->public_id])
            ->get(route('exports.download', ['artifact' => $artifactPublicId]));

        $response->assertOk();
        $response->assertDownload('admin-users.csv');
    }

    public function test_download_rejects_a_different_requesting_user(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        [$user, $team] = $this->userAndTeam();
        $otherUser = User::factory()->create(['public_id' => '01J00000000000000000000063']);
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $artifactPublicId = $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');

        $this->expectException(ReportArtifactNotDownloadable::class);

        $this->app->make(ReportExportArtifactAccess::class)->download(
            artifactPublicId: $artifactPublicId,
            actorPublicId: (string) $otherUser->public_id,
            activeTeamPublicId: (string) $team->public_id,
        );
    }

    public function test_download_rejects_audit_export_when_audit_permission_is_revoked(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, auditExport: true));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $artifactPublicId = $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv');
        $this->app->bind(ModuleGate::class, DenyAuditExportReportModuleGate::class);

        $this->expectException(\RuntimeException::class);
        $this->app->make(ReportExportArtifactAccess::class)->download(
            artifactPublicId: $artifactPublicId,
            actorPublicId: (string) $user->public_id,
            activeTeamPublicId: (string) $team->public_id,
        );
    }

    public function test_csv_generation_publishes_private_available_artifact_from_registered_provider(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);

        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Available->value,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'export_request_id' => $this->numericId($artifact->export_request_id ?? null),
            'status' => ReportExportStatus::Available->value,
            'content_type' => 'text/csv; charset=UTF-8',
            'size_bytes' => $this->numericId($artifact->size_bytes ?? null),
        ]);
        $this->assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATIONS, [
            'type' => 'report_export.available',
            'severity' => 'success',
            'title' => 'notifications.exports.available.title',
            'deep_link_url' => '/exports/'.$this->stringValue($artifact->public_id ?? null).'/download',
        ]);
        $notification = DB::table(NotificationsDatabaseTable::NOTIFICATIONS)->where('type', 'report_export.available')->first();

        self::assertNotNull($notification);
        $notificationData = json_decode($this->stringValue($notification->data ?? null), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($notificationData)) {
            self::fail('Expected report export notification data to be a JSON object.');
        }

        self::assertSame('notifications.exports.available.title', $notificationData['title_key'] ?? null);
        self::assertSame('notifications.exports.available.body', $notificationData['body_key'] ?? null);
        self::assertSame('Admin users', $notificationData['report_name'] ?? null);

        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $artifact->file_object_public_id)->first();

        self::assertNotNull($file);
        self::assertSame('clean', $file->scan_state);
        $path = $this->stringValue($file->path ?? null);

        Storage::disk('atlas_files')->assertExists($path);
        $contents = Storage::disk('atlas_files')->get($path);

        if ($contents === null) {
            self::fail('Expected generated CSV contents.');
        }

        $lines = array_values(array_filter(explode("\n", trim($contents)), static fn (string $line): bool => $line !== ''));

        self::assertSame(['Public ID', 'Email', 'Status'], str_getcsv($lines[0]));
        self::assertSame(['01J000000000000000000000AA', 'anna@example.test', "'=active"], str_getcsv($lines[1]));
        self::assertSame(['Total rows', '1'], str_getcsv($lines[2]));
        self::assertStringNotContainsString('internal-token', $contents);
    }

    public function test_generation_rechecks_audit_export_permission(): void
    {
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, auditExport: true));
        $this->app->bind(ModuleGate::class, DenyAuditExportReportModuleGate::class);
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->expectException(\RuntimeException::class);
        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);
    }

    public function test_xlsx_generation_publishes_private_available_artifact_from_registered_provider(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Xlsx));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);

        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Available->value,
            'format' => ReportExportFormat::Xlsx->value,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'export_request_id' => $this->numericId($artifact->export_request_id ?? null),
            'status' => ReportExportStatus::Available->value,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $artifact->file_object_public_id)->first();

        self::assertNotNull($file);
        $path = $this->stringValue($file->path ?? null);
        Storage::disk('atlas_files')->assertExists($path);
        $xlsxPath = Storage::disk('atlas_files')->path($path);
        $spreadsheet = IOFactory::load($xlsxPath);

        try {
            $sheet = $spreadsheet->getActiveSheet();

            self::assertSame('Public ID', $sheet->getCell('A1')->getValue());
            self::assertSame('Email', $sheet->getCell('B1')->getValue());
            self::assertSame('Status', $sheet->getCell('C1')->getValue());
            self::assertSame('01J000000000000000000000AA', $sheet->getCell('A2')->getValue());
            self::assertSame('anna@example.test', $sheet->getCell('B2')->getValue());
            self::assertSame("'=active", $sheet->getCell('C2')->getValue());
            self::assertNull($sheet->getCell('D1')->getValue());
            self::assertSame('Total rows', $sheet->getCell('A4')->getValue());
            self::assertSame('1', $sheet->getCell('B4')->getValue());
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function test_exports_intersect_visible_columns_with_authorized_columns(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot(
            user: $user,
            team: $team,
            visibleColumns: ['public_id', 'email', 'status', 'secret'],
        ));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);
        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $artifact->file_object_public_id)->first();

        self::assertNotNull($file);
        $contents = Storage::disk('atlas_files')->get($this->stringValue($file->path ?? null));

        if ($contents === null) {
            self::fail('Expected generated CSV contents.');
        }

        $lines = array_values(array_filter(explode("\n", trim($contents)), static fn (string $line): bool => $line !== ''));

        self::assertSame(['Public ID', 'Email', 'Status'], str_getcsv($lines[0]));
        self::assertStringNotContainsString('internal-token', $contents);
        self::assertStringNotContainsString('Secret', $contents);
    }

    public function test_render_credentials_are_short_lived_hashed_bound_and_one_time(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        Config::set('atlas.exports.render_token_ttl_seconds', 120);
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Pdf));

        $issued = $this->app->make(ReportRenderCredentialIssuer::class)->issue($request->publicId);
        $record = DB::table(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS)->where('public_id', $issued->publicId)->first();

        self::assertNotNull($record);
        self::assertNotSame($issued->token, $record->token_hash);
        self::assertSame(hash('sha256', $issued->token), $record->token_hash);
        self::assertSame(['email', 'public_id', 'status'], $this->jsonStringList($record->allowed_columns ?? null));
        $dataset = $this->jsonObject($record->allowed_dataset ?? null);
        self::assertSame('admin.users', $dataset['report_key'] ?? null);
        self::assertSame(['search' => 'anna'], $dataset['filters'] ?? null);

        $resolved = $this->app->make(ReportRenderCredentialAccess::class)->resolve($issued->token);

        self::assertSame($issued->publicId, $resolved->publicId);
        self::assertSame($request->publicId, $resolved->request->publicId);
        self::assertSame(ReportExportFormat::Pdf, $resolved->request->format);
        self::assertSame((string) $user->public_id, $resolved->request->requestingUserPublicId);
        self::assertSame((string) $team->public_id, $resolved->request->activeTeamPublicId);

        $this->app->make(ReportRenderCredentialAccess::class)->consume($resolved->publicId);
        $this->expectException(ReportRenderCredentialInvalid::class);
        $this->app->make(ReportRenderCredentialAccess::class)->resolve($issued->token);
    }

    public function test_pdf_generation_uses_render_credentials_and_playwright_renderer(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Pdf));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);

        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Available->value,
            'format' => ReportExportFormat::Pdf->value,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'export_request_id' => $this->numericId($artifact->export_request_id ?? null),
            'status' => ReportExportStatus::Available->value,
            'content_type' => 'application/pdf',
        ]);
        self::assertSame(1, DB::table(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS)
            ->where('report_key', 'admin.users')
            ->whereNotNull('consumed_at')
            ->count());

        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $artifact->file_object_public_id)->first();

        self::assertNotNull($file);
        $path = $this->stringValue($file->path ?? null);
        Storage::disk('atlas_files')->assertExists($path);
        $contents = Storage::disk('atlas_files')->get($path);

        if ($contents === null) {
            self::fail('Expected generated PDF contents.');
        }

        self::assertStringStartsWith('%PDF-', $contents);
        self::assertGreaterThan(1000, strlen($contents));
    }

    public function test_pdf_generation_renders_multipage_tables_with_chromium(): void
    {
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->bind('exports.test.admin_users_chart_provider', fn (): FakeReportChartProvider => new FakeReportChartProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        $this->app->tag(['exports.test.admin_users_chart_provider'], 'atlas.export_chart_providers');
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');

        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot(
            user: $user,
            team: $team,
            format: ReportExportFormat::Pdf,
            rowCount: 180,
        ));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);

        $artifact = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('status', ReportExportStatus::Available->value)->first();

        self::assertNotNull($artifact);
        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $artifact->file_object_public_id)->first();

        self::assertNotNull($file);
        $contents = Storage::disk('atlas_files')->get($this->stringValue($file->path ?? null));

        if ($contents === null) {
            self::fail('Expected generated PDF contents.');
        }

        self::assertStringStartsWith('%PDF-', $contents);
        self::assertGreaterThanOrEqual(2, substr_count($contents, '/Type /Page'));
        self::assertGreaterThan(2000, strlen($contents));
        self::assertStringContainsString('/Count', $contents);
    }

    public function test_browser_print_renders_shared_report_layout_with_local_inline_fonts(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(EffectivePermissionChecker::class, AllowAllEffectivePermissionChecker::class);
        $this->app->bind('exports.test.admin_users_provider', fn (): FakeReportDataProvider => new FakeReportDataProvider);
        $this->app->bind('exports.test.admin_users_chart_provider', fn (): FakeReportChartProvider => new FakeReportChartProvider);
        $this->app->tag(['exports.test.admin_users_provider'], 'atlas.export_data_providers');
        $this->app->tag(['exports.test.admin_users_chart_provider'], 'atlas.export_chart_providers');
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::BrowserPrint));

        $response = $this->actingAs($user)
            ->withSession(['active_team_public_id' => (string) $team->public_id])
            ->get('/exports/'.$request->publicId.'/print');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('window.print()', false);
        $response->assertSee('@page', false);
        $response->assertSee('display: table-header-group', false);
        $response->assertSee('page-break-inside: avoid', false);
        $response->assertSee('counter(page)', false);
        $response->assertSee('data:font/woff2;base64,', false);
        $response->assertSee('data:image/svg+xml;base64,', false);
        $response->assertSee('Admin users');
        $response->assertSee('Report charts');
        $response->assertSee('Cases by status');
        $response->assertSee('Open cases');
        $response->assertSee('<svg viewBox="0 0 100 10"', false);
        $response->assertSee('Atlas');
        $response->assertSee('Atlas export.');
        $response->assertSee('Total rows: 1.');
        $response->assertSee('anna@example.test');
        $response->assertDontSee('/build/assets', false);
        $response->assertDontSee('internal-token');
        self::assertSame(1, DB::table(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS)
            ->where('report_key', 'admin.users')
            ->whereNotNull('consumed_at')
            ->count());
    }

    public function test_pdf_generation_fails_without_publishing_when_required_visuals_are_not_ready(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        $this->app->bind(ManagedProcessRunner::class, FakeReportManagedProcessRunner::class);
        $this->app->bind('exports.test.not_ready_probe', fn (): FakeNotReadyRenderProbe => new FakeNotReadyRenderProbe);
        $this->app->tag(['exports.test.not_ready_probe'], 'atlas.export_render_readiness_probes');
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Pdf));
        $runPublicId = $this->app->make(ReportExportGenerationDispatcher::class)->dispatch(
            requestPublicId: $request->publicId,
            actorPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );

        try {
            $this->app->make(ReportExportGenerationProcessHandler::class)->handle($runPublicId);
            self::fail('Expected PDF generation to fail when a required visual is not ready.');
        } catch (ReportRenderVisualsNotReady) {
        }

        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Failed->value,
            'safe_error_summary' => 'Report [admin.users] is not ready for PDF rendering: chart canvas did not signal ready',
        ]);
        $this->assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATIONS, [
            'type' => 'report_export.failed',
            'severity' => 'warning',
            'title' => 'notifications.exports.failed.title',
        ]);
        $failedNotification = DB::table(NotificationsDatabaseTable::NOTIFICATIONS)->where('type', 'report_export.failed')->first();

        self::assertNotNull($failedNotification);
        $failedNotificationData = json_decode($this->stringValue($failedNotification->data ?? null), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($failedNotificationData)) {
            self::fail('Expected failed report export notification data to be a JSON object.');
        }

        self::assertSame('notifications.exports.failed.title', $failedNotificationData['title_key'] ?? null);
        self::assertSame('notifications.exports.failed.body', $failedNotificationData['body_key'] ?? null);
        self::assertSame('Admin users', $failedNotificationData['report_name'] ?? null);
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, 0);
        $this->assertDatabaseCount(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS, 0);
    }

    public function test_expired_render_credentials_are_rejected(): void
    {
        $this->app->bind(ModuleGate::class, AllowAllReportModuleGate::class);
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team, ReportExportFormat::Pdf));
        $issued = $this->app->make(ReportRenderCredentialIssuer::class)->issue($request->publicId);

        DB::table(ExportsDatabaseTable::REPORT_RENDER_CREDENTIALS)->where('public_id', $issued->publicId)->update([
            'expires_at' => new DateTimeImmutable('2000-01-01 00:00:00 UTC'),
            'updated_at' => now(),
        ]);

        $this->expectException(ReportRenderCredentialInvalid::class);
        $this->app->make(ReportRenderCredentialAccess::class)->resolve($issued->token);
    }

    public function test_cleanup_expires_artifacts_requests_and_deletes_files_through_files_lifecycle(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $artifactPublicId = $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv', expired: true);
        $filePublicId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->where('public_id', $artifactPublicId)->value('file_object_public_id');

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->update([
            'expires_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $result = $this->app->make(ReportExportMaintenance::class)->cleanupExpired(new DateTimeImmutable('now'));

        self::assertSame(1, $result->expiredRequests);
        self::assertSame(1, $result->expiredArtifacts);
        self::assertSame(1, $result->deletedFiles);
        self::assertSame(0, $result->failedFileDeletes);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS, [
            'public_id' => $artifactPublicId,
            'status' => ReportExportStatus::Expired->value,
        ]);
        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Expired->value,
        ]);
        $this->assertDatabaseMissing(FilesDatabaseTable::FILE_OBJECTS, [
            'public_id' => $filePublicId,
            'deleted_at' => null,
        ]);
    }

    public function test_cleanup_expired_command_runs_report_retention_cleanup(): void
    {
        [$user, $team] = $this->userAndTeam();
        $request = $this->app->make(ReportExportRequestRecorder::class)->record($this->snapshot($user, $team));
        $requestId = DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->value('id');
        $this->insertAvailableArtifact($this->numericId($requestId), $this->numericId($user->id), 'admin-users.csv', expired: true);

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $request->publicId)->update([
            'expires_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        self::assertSame(0, Artisan::call('exports:cleanup-expired'));
        self::assertStringContainsString(
            'Expired 1 request(s), expired 1 artifact(s), deleted 1 file(s), failed 0 file delete(s).',
            Artisan::output(),
        );

        $this->assertDatabaseHas(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS, [
            'public_id' => $request->publicId,
            'status' => ReportExportStatus::Expired->value,
        ]);
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
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'is_head_manager' => false,
            'valid_from' => now()->subMinute(),
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    /**
     * @param  list<string>  $visibleColumns
     */
    private function snapshot(
        User $user,
        Team $team,
        ReportExportFormat $format = ReportExportFormat::Csv,
        array $visibleColumns = ['public_id', 'email', 'status'],
        bool $auditExport = false,
        ?int $estimatedRowCount = null,
        ?int $rowCount = null,
    ): ReportExportRequestSnapshot {
        return new ReportExportRequestSnapshot(
            reportKey: 'admin.users',
            reportName: 'Admin users',
            moduleKey: 'users',
            format: $format,
            activeTeamId: (int) $team->id,
            activeTeamPublicId: (string) $team->public_id,
            requestingUserId: (int) $user->id,
            requestingUserPublicId: (string) $user->public_id,
            filters: array_filter(
                ['search' => 'anna', 'row_count' => $rowCount],
                static fn (mixed $value): bool => $value !== null,
            ),
            sorting: [['id' => 'email', 'desc' => false]],
            visibleColumns: $visibleColumns,
            columnOrder: $visibleColumns,
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
            auditExport: $auditExport,
            estimatedRowCount: $estimatedRowCount,
        );
    }

    private function insertAvailableArtifact(int $requestId, int $userId, string $filename, bool $expired = false): string
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/'.$filename, 'export-data');
        $filePublicId = (string) Str::ulid();
        $artifactPublicId = (string) Str::ulid();

        $fileObjectId = DB::table(FilesDatabaseTable::FILE_OBJECTS)->insertGetId([
            'public_id' => $filePublicId,
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

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_REQUESTS)->where('id', $requestId)->update([
            'status' => ReportExportStatus::Available->value,
            'updated_at' => now(),
        ]);

        DB::table(ExportsDatabaseTable::REPORT_EXPORT_ARTIFACTS)->insert([
            'public_id' => $artifactPublicId,
            'export_request_id' => $requestId,
            'file_object_id' => $fileObjectId,
            'file_object_public_id' => $filePublicId,
            'status' => ReportExportStatus::Available->value,
            'filename' => $filename,
            'content_type' => 'text/csv',
            'size_bytes' => 10,
            'checksum_sha256' => str_repeat('b', 64),
            'created_by_user_id' => $userId,
            'available_at' => now(),
            'failed_at' => null,
            'expires_at' => $expired ? now()->subDay() : now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $artifactPublicId;
    }

    private function numericId(mixed $value): int
    {
        if (! is_numeric($value)) {
            self::fail('Expected a numeric database identifier.');
        }

        return (int) $value;
    }

    private function stringValue(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            self::fail('Expected a non-empty string value.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function jsonStringList(mixed $value): array
    {
        $decoded = $this->jsonObjectOrList($value);
        $strings = [];

        foreach ($decoded as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $value): array
    {
        $decoded = $this->jsonObjectOrList($value);
        $object = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function jsonObjectOrList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            self::fail('Expected a JSON string.');
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            self::fail('Expected a JSON array or object.');
        }

        return $decoded;
    }
}

final class AllowAllReportModuleGate implements ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return true;
    }
}

final class DenyAuditExportReportModuleGate implements ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        if ($request->requiredPermission === ReportsPermissionCatalog::AUDIT_EXPORT) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::PermissionDenied);
        }

        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return $this->inspect($request)->allowed;
    }
}

final class DenyReportRequestModuleGate implements ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        if ($request->moduleKey === 'exports' && $request->requiredPermission === ReportsPermissionCatalog::REQUEST) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::PermissionDenied);
        }

        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return $this->inspect($request)->allowed;
    }
}

final class DenyUsersReportModuleGate implements ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        if ($request->moduleKey === 'users') {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::PermissionDenied);
        }

        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return $this->inspect($request)->allowed;
    }
}

final class AllowAllEffectivePermissionChecker implements EffectivePermissionChecker
{
    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision
    {
        return new EffectivePermissionDecision(true, 'allowed');
    }
}

final class DenyAllEffectivePermissionChecker implements EffectivePermissionChecker
{
    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision
    {
        return new EffectivePermissionDecision(false, 'denied');
    }
}

final class FakeReportDataProvider implements ReportExportDataProvider
{
    public function reportKey(): string
    {
        return 'admin.users';
    }

    public function columns(ReportExportGenerationRequest $request): array
    {
        return [
            new ReportExportColumn('public_id', 'Public ID'),
            new ReportExportColumn('email', 'Email'),
            new ReportExportColumn('status', 'Status'),
            new ReportExportColumn('secret', 'Secret'),
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rowCount = is_numeric($request->filters['row_count'] ?? null) ? max(1, (int) $request->filters['row_count']) : 1;

        for ($row = 1; $row <= $rowCount; $row++) {
            yield [
                'public_id' => $row === 1 ? '01J000000000000000000000AA' : sprintf('01J000000000000000%08d', $row),
                'email' => $row === 1 ? 'anna@example.test' : sprintf('anna+%03d@example.test', $row),
                'status' => '=active',
                'secret' => 'internal-token',
            ];
        }
    }
}

final class FakeAdminUsersDataTableExportProvider implements AdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return 'admin.users';
    }

    public function reportKey(): string
    {
        return $this->tableKey();
    }

    public function tableName(): string
    {
        return 'Admin users';
    }

    public function owningModuleKey(): string
    {
        return 'users';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-users-export-v1';
    }

    public function tableDefinition(): TableDefinition
    {
        return new TableDefinition('admin.users', [
            new TableColumn('publicId'),
            new TableColumn('email'),
            new TableColumn('secret', sortable: false, searchable: false, defaultVisible: false),
        ], 'email');
    }

    public function allowedExportColumns(AdminDataTableExportContext $context): array
    {
        return ['publicId', 'email'];
    }

    public function supportedFormats(AdminDataTableExportContext $context): array
    {
        return [
            ReportExportFormat::Csv,
            ReportExportFormat::Xlsx,
            ReportExportFormat::Pdf,
            ReportExportFormat::BrowserPrint,
        ];
    }

    public function columns(ReportExportGenerationRequest $request): array
    {
        return [
            new ReportExportColumn('publicId', 'Public ID'),
            new ReportExportColumn('email', 'Email'),
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        yield [
            'publicId' => '01J000000000000000000000AA',
            'email' => 'anna@example.test',
        ];
    }
}

final class FakeUserCredentialAccountDirectory implements UserCredentialAccountDirectory
{
    public function allOptions(): array
    {
        return [];
    }

    public function allAdminRows(): array
    {
        return [
            new AdminUserCredentialAccount(
                id: 1,
                publicId: '01J000000000000000000000AA',
                name: 'Anna Admin',
                email: 'anna@example.test',
                isActive: true,
                emailVerified: true,
                firstPasswordSet: true,
                loginLocked: false,
                mfaEnabled: true,
                online: false,
                accountSensitivity: 'standard',
                emailVerifiedAt: '2026-07-20T08:00:00+00:00',
                twoFactorConfirmedAt: '2026-07-20T08:30:00+00:00',
                firstPasswordSetAt: '2026-07-20T09:00:00+00:00',
                deactivatedAt: null,
                failedLoginAttempts: 0,
                loginLockCount: 0,
                loginLockedUntil: null,
                createdAt: '2026-07-20T08:00:00+00:00',
                updatedAt: '2026-07-20T09:00:00+00:00',
            ),
            new AdminUserCredentialAccount(
                id: 2,
                publicId: '01J000000000000000000000AB',
                name: 'Bartosz Operator',
                email: 'bartosz@example.test',
                isActive: true,
                emailVerified: true,
                firstPasswordSet: true,
                loginLocked: false,
                mfaEnabled: false,
                online: false,
                accountSensitivity: 'standard',
                emailVerifiedAt: '2026-07-20T08:00:00+00:00',
                twoFactorConfirmedAt: null,
                firstPasswordSetAt: '2026-07-20T09:00:00+00:00',
                deactivatedAt: null,
                failedLoginAttempts: 0,
                loginLockCount: 0,
                loginLockedUntil: null,
                createdAt: '2026-07-20T08:00:00+00:00',
                updatedAt: '2026-07-20T09:00:00+00:00',
            ),
        ];
    }

    public function findAdminRow(string $publicId): ?AdminUserCredentialAccount
    {
        foreach ($this->allAdminRows() as $row) {
            if ($row->publicId === $publicId) {
                return $row;
            }
        }

        return null;
    }

    public function publicIdExists(string $publicId): bool
    {
        return $this->findAdminRow($publicId) !== null;
    }

    public function emailExists(string $email, ?string $exceptPublicId = null): bool
    {
        foreach ($this->allAdminRows() as $row) {
            if ($row->email === $email && $row->publicId !== $exceptPublicId) {
                return true;
            }
        }

        return false;
    }

    public function updateIdentity(
        string $publicId,
        string $name,
        string $email,
        string $accountSensitivity,
    ): ?AdminUserCredentialAccount {
        return $this->findAdminRow($publicId);
    }

    public function verifyEmail(string $publicId): ?AdminUserCredentialAccount
    {
        return $this->findAdminRow($publicId);
    }

    public function requireEmailVerification(string $publicId): ?AdminUserCredentialAccount
    {
        return $this->findAdminRow($publicId);
    }
}

final class FakeReportChartProvider implements ReportChartProvider
{
    public function reportKey(): string
    {
        return 'admin.users';
    }

    public function charts(ReportExportGenerationRequest $request): array
    {
        return [
            new ReportChartDefinition(
                key: 'cases-by-status',
                title: 'Cases by status',
                description: 'Open cases by lifecycle status.',
                unit: 'cases',
                series: [
                    new ReportChartSeries('Open cases', [
                        new ReportChartPoint('New', 12),
                        new ReportChartPoint('In progress', 7),
                        new ReportChartPoint('Closed', 3),
                    ]),
                ],
            ),
        ];
    }
}

final class FakeNotReadyRenderProbe implements ReportRenderReadinessProbe
{
    public function reportKey(): string
    {
        return 'admin.users';
    }

    public function check(ReportExportGenerationRequest $request): ReportRenderReadinessResult
    {
        return ReportRenderReadinessResult::notReady($request->reportKey, 'chart canvas did not signal ready');
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
        $actorId = DB::table(IdentityDatabaseTable::USERS)->where('public_id', $actorPublicId)->value('id');
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        DB::table(ManagedProcessesDatabaseTable::RUNS)->insert([
            'public_id' => $publicId,
            'process_key' => $processKey,
            'module_key' => 'exports',
            'scope' => 'team',
            'team_id' => is_numeric($teamId) ? (int) $teamId : null,
            'actor_user_id' => is_numeric($actorId) ? (int) $actorId : null,
            'source_type' => $sourceType,
            'input_snapshot' => json_encode($input ?? ['_input' => 'none'], JSON_THROW_ON_ERROR),
            'queue_connection' => 'sync',
            'queue_name' => 'exports',
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
