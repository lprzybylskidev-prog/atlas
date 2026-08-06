<?php

declare(strict_types=1);

namespace Tests\Feature\ManagedProcesses;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ManagedProcessHandler;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessDefinition;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessLogEntry;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\ProcessPermissions;
use App\Modules\Optional\ManagedProcesses\Application\DTOs\RetryPolicy;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessLogSeverity;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Permissions\ManagedProcessesPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ManagedProcessesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_admin_can_start_and_inspect_managed_process_logs(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateModules($team, ['managed_processes']);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->has('navigation.breadcrumbs', 4)
                ->where('navigation.breadcrumbs.0.label', 'Atlas')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Procesy')
                ->where('navigation.breadcrumbs.3.label', 'Uruchomienia')
                ->where('table.key', 'admin.managed-processes.runs')
                ->where('table.state.filters.status', 'all')
                ->where('auth.availableAdminRoutes', function (Collection $routes): bool {
                    return $routes->contains('admin.managed-processes.index');
                })
                ->missing('definitions')
                ->missing('schedules'));

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/managed-processes/run', [
                'process_key' => 'test.maintenance.rebuild-risk-cache',
                'source_type' => 'manual',
                'input' => ['segment' => 'test-overdue', 'dry_run' => true],
            ])
            ->assertRedirect();

        $runPublicId = basename((string) $response->headers->get('Location'));

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUNS, [
            'public_id' => $runPublicId,
            'process_key' => 'test.maintenance.rebuild-risk-cache',
            'status' => 'succeeded_with_warnings',
        ]);
        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS, [
            'severity' => 'warning',
            'event_type' => 'stage',
            'stage' => 'normalize',
        ]);
        $this->assertDatabaseHas(DatabaseTable::REALTIME_EVENTS, [
            'topic' => 'operation-progress',
            'event_type' => 'operation.progress',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/'.$runPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Show')
                ->has('navigation.breadcrumbs', 5)
                ->where('navigation.breadcrumbs.0.label', 'Atlas')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Procesy')
                ->where('navigation.breadcrumbs.3.label', 'Uruchomienia')
                ->where('navigation.breadcrumbs.4.label', "Szczegóły uruchomienia · {$runPublicId}")
                ->where('run.publicId', $runPublicId)
                ->where('run.canRetry', true)
                ->where('filterOptions.severities', fn (Collection $severities): bool => $severities->contains('info'))
                ->has('logs', 7));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes?status=failed')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->where('table.state.filters.status', 'failed')
                ->has('runs', 0));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes?status=succeeded_with_warnings&handling=needs_attention')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->where('table.state.filters.handling', 'needs_attention')
                ->where('runs.0.publicId', $runPublicId)
                ->where('runs.0.handlingStatus', 'needs_attention')
                ->where('runs.0.canAcknowledge', true)
                ->where('summary.warnings24h', 1));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/managed-processes/acknowledge', [
                'runs' => [$runPublicId],
                'reason' => 'Reviewed warning output.',
            ])
            ->assertRedirect(route('admin.managed-processes.index'))
            ->assertSessionHas('flash.messages.0.key', 'flash.managed_processes.acknowledge_single');

        $runId = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('public_id', $runPublicId)->value('id');

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS, [
            'process_run_id' => $runId,
            'acknowledged_by_user_id' => $admin->id,
            'reason' => 'Reviewed warning output.',
        ]);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'managed_processes',
            'action' => 'managed_process.run_acknowledge',
            'target_public_id' => $runPublicId,
            'result' => 'succeeded',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes?status=succeeded_with_warnings&handling=needs_attention')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->where('summary.warnings24h', 0)
                ->has('runs', 0));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes?status=succeeded_with_warnings&handling=handled')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->where('table.state.filters.handling', 'handled')
                ->where('runs.0.publicId', $runPublicId)
                ->where('runs.0.handlingStatus', 'handled')
                ->where('runs.0.canAcknowledge', false)
                ->where('summary.handled', 1));

        $retryResponse = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/managed-processes/'.$runPublicId.'/retry', [
                'reason' => 'Retry after warnings.',
            ])
            ->assertRedirect();

        $retryRunPublicId = basename((string) $retryResponse->headers->get('Location'));
        $originalRunId = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('public_id', $runPublicId)->value('id');

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUNS, [
            'public_id' => $retryRunPublicId,
            'source_type' => 'retry',
            'retry_of_run_id' => $originalRunId,
        ]);
    }

    public function test_import_execution_uses_managed_process_run_and_import_detail(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateModules($team, ['managed_processes', 'imports', 'integrations']);

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/managed-processes/run', [
                'process_key' => 'test.imports.debtor-ledger',
                'source_type' => 'file_import',
                'input' => ['source_type' => 'csv', 'idempotency_key' => 'test-import-csv'],
            ])
            ->assertRedirect();
        $runPublicId = basename((string) $response->headers->get('Location'));

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUNS, [
            'public_id' => $runPublicId,
            'process_key' => 'test.imports.debtor-ledger',
            'status' => 'succeeded_with_warnings',
        ]);
        $this->assertDatabaseHas(DatabaseTable::IMPORT_EXECUTIONS, [
            'source_type' => 'csv',
            'idempotency_key' => 'test-import-csv',
            'idempotency_state' => 'completed',
        ]);
        $this->assertDatabaseHas(DatabaseTable::IMPORT_ROW_ERRORS, [
            'field_name' => 'currency',
            'error_code' => 'currency.unsupported_test',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Runs')
                ->where('runs.0.publicId', $runPublicId)
                ->where('runs.0.importKey', 'debtor-ledger-test')
                ->where('runs.0.idempotencyKey', 'test-import-csv'));

        $this->assertFalse(Route::has('admin.imports.index'));
        $this->assertFalse(Route::has('admin.managed-processes.imports.index'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/'.$runPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Show')
                ->where('importExecution.idempotencyKey', 'test-import-csv')
                ->has('importExecution.errors', 2));
    }

    public function test_terminal_notification_omits_admin_deep_link_when_process_details_are_not_available(): void
    {
        [$admin, $team] = $this->adminWithTeam();

        $runPublicId = $this->app->make(ManagedProcessRunner::class)->start(
            processKey: 'test.exports.generate',
            sourceType: 'system',
            input: ['export_request_public_id' => '01JEXPORT0000000000000001'],
            actorPublicId: (string) $admin->public_id,
            teamPublicId: (string) $team->public_id,
        );

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_RUNS, [
            'public_id' => $runPublicId,
            'process_key' => 'test.exports.generate',
            'status' => ProcessRunStatus::Succeeded->value,
        ]);
        $this->assertDatabaseHas(DatabaseTable::NOTIFICATIONS, [
            'type' => 'managed_process.succeeded',
            'title' => 'notifications.managed_process.succeeded.title',
            'body' => 'notifications.managed_process.succeeded.body',
            'deep_link_url' => null,
        ]);

        $notification = DB::table(DatabaseTable::NOTIFICATIONS)
            ->where('type', 'managed_process.succeeded')
            ->first(['data']);

        self::assertNotNull($notification);
        $data = json_decode($this->recordString($notification, 'data') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('Test export generation', $data['process_name'] ?? null);
        self::assertSame('notifications.managed_process.succeeded.title', $data['title_key'] ?? null);
        self::assertSame('notifications.managed_process.succeeded.body', $data['body_key'] ?? null);
        self::assertArrayNotHasKey('status', $data);
    }

    public function test_admin_can_create_and_disable_managed_process_schedule(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateModules($team, ['managed_processes']);

        $response = $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/schedules/create');

        $response
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Schedules/Create')
                ->has('navigation.breadcrumbs', 5)
                ->where('navigation.breadcrumbs.0.label', 'Atlas')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Procesy')
                ->where('navigation.breadcrumbs.3.label', 'Harmonogramy')
                ->where('navigation.breadcrumbs.4.label', 'Utwórz harmonogram'));

        $this->assertTrue($this->containsRow(
            $response->inertiaProps('definitions'),
            static fn (array $definition): bool => ($definition['key'] ?? null) === 'test.maintenance.rebuild-risk-cache',
        ));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->post('/admin/managed-processes/schedules', [
                'process_key' => 'test.maintenance.rebuild-risk-cache',
                'cron_expression' => '15 2 * * 1-5',
                'reason' => 'Feature test schedule.',
            ])
            ->assertRedirect(route('admin.managed-processes.schedules.index'));

        $schedule = $this->app['db']->table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->first();
        $this->assertNotNull($schedule);
        $schedulePublicId = is_scalar($schedule->public_id ?? null) ? (string) $schedule->public_id : '';

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->patch('/admin/managed-processes/schedules/'.$schedulePublicId.'/disable', [
                'reason' => 'Feature test disable.',
            ])
            ->assertRedirect(route('admin.managed-processes.schedules.index'));

        $this->assertDatabaseHas(DatabaseTable::MANAGED_PROCESS_SCHEDULES, [
            'public_id' => $schedulePublicId,
            'enabled' => false,
            'cron_expression' => '15 2 * * 1-5',
        ]);

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/schedules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Schedules')
                ->has('navigation.breadcrumbs', 4)
                ->where('navigation.breadcrumbs.0.label', 'Atlas')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Procesy')
                ->where('navigation.breadcrumbs.3.label', 'Harmonogramy')
                ->where('table.key', 'admin.managed-processes.schedules')
                ->where('table.state.filters.enabled', 'all')
                ->where('schedules.0.publicId', $schedulePublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/definitions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Definitions')
                ->has('navigation.breadcrumbs', 4)
                ->where('navigation.breadcrumbs.0.label', 'Atlas')
                ->where('navigation.breadcrumbs.1.label', 'Panel administratora')
                ->where('navigation.breadcrumbs.2.label', 'Procesy')
                ->where('navigation.breadcrumbs.3.label', 'Definicje')
                ->where('table.key', 'admin.managed-processes.definitions')
                ->where('table.state.filters.manual', 'all')
                ->where('definitions', function (mixed $definitions): bool {
                    if (! is_iterable($definitions)) {
                        return false;
                    }

                    foreach ($definitions as $definition) {
                        if (is_array($definition) && ($definition['key'] ?? null) === 'test.maintenance.rebuild-risk-cache') {
                            return true;
                        }
                    }

                    return false;
                }));
    }

    public function test_running_progress_preserves_original_started_timestamp(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $runPublicId = (string) Str::ulid();

        DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->insert([
            'public_id' => $runPublicId,
            'process_key' => 'test.maintenance.rebuild-risk-cache',
            'module_key' => 'managed_processes',
            'scope' => 'team',
            'team_id' => $team->id,
            'actor_user_id' => $admin->id,
            'source_type' => 'manual',
            'input_snapshot' => json_encode(['_input' => 'none'], JSON_THROW_ON_ERROR),
            'queue_connection' => 'redis',
            'queue_name' => 'managed-processes',
            'status' => ProcessRunStatus::Queued->value,
            'current_stage' => 'queued',
            'progress_current' => 0,
            'progress_total' => null,
            'progress_label' => 'Queued',
            'counters' => json_encode(['processed' => 0], JSON_THROW_ON_ERROR),
            'correlation_id' => (string) Str::uuid(),
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $runner = $this->app->make(ManagedProcessRunner::class);

        try {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 10:00:00'));
            $runner->updateProgress($runPublicId, ProcessRunStatus::Running, 'started', 0, 4, 'Running');

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 10:05:00'));
            $runner->updateProgress($runPublicId, ProcessRunStatus::Running, 'verify', 3, 4, 'Verifying');
        } finally {
            CarbonImmutable::setTestNow();
        }

        $run = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->where('public_id', $runPublicId)
            ->first(['started_at', 'current_stage', 'progress_current']);

        $this->assertNotNull($run);
        $this->assertSame('verify', $this->recordString($run, 'current_stage'));
        $this->assertSame(3, $this->recordInt($run, 'progress_current'));
        $this->assertSame(
            '2026-07-30 10:00:00',
            CarbonImmutable::parse($this->recordString($run, 'started_at'))->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();
        $this->registerTestProcesses();

        $admin = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => '01J00000000000000000000021',
            'name' => 'Operations',
            'slug' => 'operations',
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();

        $this->app['db']->table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app['db']->table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $admin->id,
            'team_id' => $team->id,
        ]);

        return [$admin, $team];
    }

    private function registerTestProcesses(): void
    {
        $this->app->bind('tests.managed_processes.maintenance_definition', fn (): ProcessDefinition => $this->makeProcessDefinition(
            key: 'test.maintenance.rebuild-risk-cache',
            moduleKey: 'managed_processes',
            label: 'Test risk cache rebuild',
            description: 'Test-only managed process fixture.',
            queueName: 'managed-processes',
        ));
        $this->app->bind('tests.managed_processes.import_definition', fn (): ProcessDefinition => $this->makeProcessDefinition(
            key: 'test.imports.debtor-ledger',
            moduleKey: 'imports',
            label: 'Test debtor ledger import',
            description: 'Test-only import process fixture.',
            queueName: 'imports',
        ));
        $this->app->bind('tests.managed_processes.export_definition', fn (): ProcessDefinition => $this->makeProcessDefinition(
            key: 'test.exports.generate',
            moduleKey: 'exports',
            label: 'Test export generation',
            description: 'Test-only export process fixture.',
            queueName: 'exports',
            runPermission: ReportsPermissionCatalog::REQUEST,
        ));
        $this->app->tag([
            'tests.managed_processes.maintenance_definition',
            'tests.managed_processes.import_definition',
            'tests.managed_processes.export_definition',
        ], 'atlas.managed_process_definitions');
        $this->app->tag([
            TestMaintenanceProcessHandler::class,
            TestImportProcessHandler::class,
            TestExportProcessHandler::class,
        ], 'atlas.managed_process_handlers');
    }

    private function makeProcessDefinition(string $key, string $moduleKey, string $label, string $description, string $queueName, string $runPermission = ManagedProcessesPermissionCatalog::RUN): ProcessDefinition
    {
        return new ProcessDefinition(
            key: $key,
            moduleKey: $moduleKey,
            label: $label,
            description: $description,
            scope: 'team',
            inputSchema: ['type' => 'object'],
            permissions: new ProcessPermissions(
                view: ManagedProcessesPermissionCatalog::SHOW,
                run: $runPermission,
                retry: ManagedProcessesPermissionCatalog::RETRY,
                cancel: ManagedProcessesPermissionCatalog::CANCEL,
                schedule: ManagedProcessesPermissionCatalog::SCHEDULES_STORE,
            ),
            queueName: $queueName,
            executionMode: 'sync',
            concurrencyPolicy: 'one_active_per_team',
            parallelism: 1,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 2, backoffSeconds: 60),
            cancellationPolicy: 'checkpoint',
            scheduleSupported: true,
            manualStartSupported: true,
        );
    }

    /**
     * @param  list<string>  $moduleKeys
     */
    private function activateModules(Team $team, array $moduleKeys): void
    {
        $activation = $this->app->make(ModuleActivationService::class);

        foreach ($moduleKeys as $moduleKey) {
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'Feature test setup',
                source: ModuleActivationSource::Manual,
            ));
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Team,
                enabled: true,
                reason: 'Feature test setup',
                teamId: $team->id,
                source: ModuleActivationSource::Manual,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];
    }

    private function recordString(object $record, string $property): string
    {
        $values = get_object_vars($record);
        $value = $values[$property] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function recordInt(object $record, string $property): int
    {
        $values = get_object_vars($record);
        $value = $values[$property] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function containsRow(mixed $rows, callable $predicate): bool
    {
        if ($rows instanceof Arrayable) {
            $rows = $rows->toArray();
        }

        if ($rows instanceof \Traversable) {
            $rows = iterator_to_array($rows, false);
        }

        if (! is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if (is_array($row) && $predicate(self::stringKeyedArray($row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}

final readonly class TestMaintenanceProcessHandler implements ManagedProcessHandler
{
    public function __construct(private ManagedProcessRunner $runner) {}

    public function processKey(): string
    {
        return 'test.maintenance.rebuild-risk-cache';
    }

    public function handle(string $runPublicId): void
    {
        foreach ([
            ['load', ProcessLogSeverity::Info, 'Loaded test records.'],
            ['normalize', ProcessLogSeverity::Warning, 'Normalized records with warnings.'],
            ['apply', ProcessLogSeverity::Info, 'Applied test changes.'],
            ['verify', ProcessLogSeverity::Info, 'Verified counters.'],
            ['finished', ProcessLogSeverity::Success, 'Test process finished.'],
        ] as [$stage, $severity, $message]) {
            $this->runner->log($runPublicId, new ProcessLogEntry($severity, 'stage', $message, $stage));
        }

        $this->runner->updateProgress(
            runPublicId: $runPublicId,
            status: ProcessRunStatus::SucceededWithWarnings,
            stage: 'finished',
            current: 4,
            total: 4,
            label: 'Finished with warnings',
            counters: ['processed' => 4, 'success' => 3, 'warning' => 1],
            resultSummary: ['processed' => 4],
        );
    }
}

final readonly class TestImportProcessHandler implements ManagedProcessHandler
{
    public function __construct(private ManagedProcessRunner $runner) {}

    public function processKey(): string
    {
        return 'test.imports.debtor-ledger';
    }

    public function handle(string $runPublicId): void
    {
        $run = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('public_id', $runPublicId)->firstOrFail();
        $input = is_string($run->input_snapshot ?? null) ? json_decode($run->input_snapshot, true) : [];
        $idempotencyKey = is_array($input) && is_string($input['idempotency_key'] ?? null) ? $input['idempotency_key'] : 'test-import';
        $importExecutionId = DB::table(DatabaseTable::IMPORT_EXECUTIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'process_run_id' => $run->id,
            'import_key' => 'debtor-ledger-test',
            'source_type' => 'csv',
            'file_object_id' => null,
            'api_reference' => null,
            'external_reference' => 'test-ledger-feed',
            'mapping_snapshot' => json_encode(['mapping' => 'test'], JSON_THROW_ON_ERROR),
            'source_metadata' => json_encode(['rows' => 4], JSON_THROW_ON_ERROR),
            'statistics' => json_encode(['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2], JSON_THROW_ON_ERROR),
            'idempotency_key' => $idempotencyKey,
            'idempotency_state' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([3, 4] as $rowNumber) {
            DB::table(DatabaseTable::IMPORT_ROW_ERRORS)->insert([
                'public_id' => (string) Str::ulid(),
                'import_execution_id' => $importExecutionId,
                'row_number' => $rowNumber,
                'field_name' => 'currency',
                'severity' => 'warning',
                'error_code' => 'currency.unsupported_test',
                'message' => 'Test import accepts PLN rows only; row was skipped.',
                'safe_context' => json_encode(['currency' => 'EUR'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->runner->log($runPublicId, new ProcessLogEntry(ProcessLogSeverity::Warning, 'row_warning', 'Skipped unsupported currency rows.', 'validate', errorCode: 'currency.unsupported_test'));
        $this->runner->updateProgress(
            runPublicId: $runPublicId,
            status: ProcessRunStatus::SucceededWithWarnings,
            stage: 'finished',
            current: 4,
            total: 4,
            label: 'Import completed with warnings',
            counters: ['processed' => 4, 'success' => 2, 'warning' => 2, 'skipped' => 2],
            resultSummary: ['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2],
        );
    }
}

final readonly class TestExportProcessHandler implements ManagedProcessHandler
{
    public function __construct(private ManagedProcessRunner $runner) {}

    public function processKey(): string
    {
        return 'test.exports.generate';
    }

    public function handle(string $runPublicId): void
    {
        $this->runner->updateProgress(
            runPublicId: $runPublicId,
            status: ProcessRunStatus::Succeeded,
            stage: 'completed',
            current: 1,
            total: 1,
            label: 'Export generated',
            counters: ['processed' => 1, 'success' => 1],
            resultSummary: ['artifact_public_id' => '01JARTIFACT00000000000001'],
        );
    }
}
