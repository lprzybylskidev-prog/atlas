<?php

declare(strict_types=1);

namespace Tests\Feature\ManagedProcesses;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ManagedProcessesAdminTest extends TestCase
{
    use RefreshDatabase;

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
                ->where('run.publicId', $runPublicId)
                ->where('run.canRetry', true)
                ->has('logs', 7));
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
                ->where('runs.0.publicId', $runPublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/imports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Imports')
                ->where('importExecutions.0.runPublicId', $runPublicId)
                ->where('importExecutions.0.idempotencyKey', 'test-import-csv'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/imports')
            ->assertRedirect(route('admin.managed-processes.imports.index'));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/'.$runPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Show')
                ->where('importExecution.idempotencyKey', 'test-import-csv')
                ->has('importExecution.errors', 2));
    }

    public function test_admin_can_create_and_disable_managed_process_schedule(): void
    {
        [$admin, $team] = $this->adminWithTeam();
        $this->activateModules($team, ['managed_processes']);

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
                ->where('schedules.0.publicId', $schedulePublicId));

        $this->actingAs($admin)
            ->withSession($this->adminSession($team))
            ->get('/admin/managed-processes/definitions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ManagedProcesses/Definitions')
                ->where('definitions', fn (mixed $definitions): bool => collect($definitions)
                    ->contains(fn (array $definition): bool => ($definition['key'] ?? null) === 'test.maintenance.rebuild-risk-cache')));
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
        $this->app->tag([
            'tests.managed_processes.maintenance_definition',
            'tests.managed_processes.import_definition',
        ], 'atlas.managed_process_definitions');
        $this->app->tag([
            TestMaintenanceProcessHandler::class,
            TestImportProcessHandler::class,
        ], 'atlas.managed_process_handlers');
    }

    private function makeProcessDefinition(string $key, string $moduleKey, string $label, string $description, string $queueName): ProcessDefinition
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
                run: ManagedProcessesPermissionCatalog::RUN,
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
