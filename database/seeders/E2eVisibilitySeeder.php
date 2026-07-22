<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class E2eVisibilitySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.test';

    public const LIMITED_EMAIL = 'limited@example.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $team = app(BootstrapTeamProvider::class)->provide('E2E Visibility Team');
        $admin = $this->user(self::ADMIN_EMAIL, 'Visibility Admin');
        $limited = $this->user(self::LIMITED_EMAIL, 'Visibility User');

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: (string) $admin->public_id,
            teamPublicId: $team->publicId,
        );
        $this->activateModules($team->publicId);
        $this->seedProcessRun((string) $admin->public_id, $team->publicId);

        app(PermissionRoleStore::class)->assignRoleToUserInTeam(
            userPublicId: (string) $limited->public_id,
            teamPublicId: $team->publicId,
            roleName: StarterRoleName::WorkspaceAccess->value,
        );

        $this->auditEvents((string) $admin->public_id, $team->publicId);
    }

    private function activateModules(string $teamPublicId): void
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($teamId)) {
            return;
        }

        $activation = app(ModuleActivationService::class);

        foreach (['integrations', 'managed_processes', 'imports', 'search'] as $moduleKey) {
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'E2E visibility setup.',
                source: ModuleActivationSource::Manual,
            ));
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Team,
                enabled: true,
                reason: 'E2E visibility setup.',
                teamId: $teamId,
                source: ModuleActivationSource::Manual,
            ));
        }
    }

    private function seedProcessRun(string $adminPublicId, string $teamPublicId): void
    {
        if (DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('process_key', 'e2e.imports.debtor-ledger')->exists()) {
            return;
        }

        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');
        $adminId = DB::table(DatabaseTable::USERS)->where('public_id', $adminPublicId)->value('id');

        if (! is_int($teamId) || ! is_int($adminId)) {
            return;
        }

        $runId = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'process_key' => 'e2e.imports.debtor-ledger',
            'module_key' => 'imports',
            'scope' => 'team',
            'team_id' => $teamId,
            'actor_user_id' => $adminId,
            'source_type' => 'file_import',
            'input_snapshot' => json_encode(['source_type' => 'csv', 'idempotency_key' => 'e2e-import-csv'], JSON_THROW_ON_ERROR),
            'queue_connection' => 'sync',
            'queue_name' => 'imports',
            'job_identifier' => null,
            'status' => 'succeeded_with_warnings',
            'current_stage' => 'finished',
            'progress_current' => 4,
            'progress_total' => 4,
            'progress_label' => 'Import completed with warnings',
            'counters' => json_encode(['processed' => 4, 'success' => 2, 'info' => 1, 'warning' => 2, 'error' => 0, 'failed' => 0, 'skipped' => 2, 'retried' => 0], JSON_THROW_ON_ERROR),
            'correlation_id' => 'e2e-import-correlation',
            'causation_id' => null,
            'retry_of_run_id' => null,
            'queued_at' => now()->subMinutes(4),
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'failed_at' => null,
            'cancelled_at' => null,
            'retried_at' => null,
            'result_summary' => json_encode(['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2], JSON_THROW_ON_ERROR),
            'safe_error_summary' => null,
            'cancel_reason' => null,
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(2),
        ]);

        foreach ([
            ['info', 'message', 'queued', 'Process run queued.', null],
            ['info', 'stage', 'started', 'Process execution started.', null],
            ['warning', 'row_warning', 'validate', 'Skipped unsupported currency rows.', 'currency.unsupported_e2e'],
        ] as [$severity, $eventType, $stage, $message, $errorCode]) {
            DB::table(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS)->insert([
                'public_id' => (string) Str::ulid(),
                'process_run_id' => $runId,
                'occurred_at' => now()->subMinutes(2),
                'severity' => $severity,
                'event_type' => $eventType,
                'stage' => $stage,
                'message' => $message,
                'safe_context' => null,
                'row_number' => null,
                'entity_public_id' => null,
                'external_reference' => null,
                'source_reference' => null,
                'error_code' => $errorCode,
                'exception_class' => null,
                'retryable' => null,
                'correlation_id' => 'e2e-import-correlation',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ]);
        }

        $importExecutionId = DB::table(DatabaseTable::IMPORT_EXECUTIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'process_run_id' => $runId,
            'import_key' => 'debtor-ledger-e2e',
            'source_type' => 'csv',
            'file_object_id' => null,
            'api_reference' => null,
            'external_reference' => 'e2e-ledger-feed',
            'mapping_snapshot' => json_encode(['mapping' => 'e2e'], JSON_THROW_ON_ERROR),
            'source_metadata' => json_encode(['rows' => 4], JSON_THROW_ON_ERROR),
            'statistics' => json_encode(['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'e2e-import-csv',
            'idempotency_state' => 'completed',
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(2),
        ]);

        foreach ([3, 4] as $rowNumber) {
            DB::table(DatabaseTable::IMPORT_ROW_ERRORS)->insert([
                'public_id' => (string) Str::ulid(),
                'import_execution_id' => $importExecutionId,
                'row_number' => $rowNumber,
                'field_name' => 'currency',
                'severity' => 'warning',
                'error_code' => 'currency.unsupported_e2e',
                'message' => 'E2E import accepts PLN rows only; row was skipped.',
                'safe_context' => json_encode(['currency' => 'EUR'], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ]);
        }
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();

        return $user;
    }

    private function auditEvents(string $adminPublicId, string $teamPublicId): void
    {
        $recorder = app(AuditRecorder::class);

        $recorder->record(new AuditEvent(
            module: 'identity',
            action: 'e2e.audit.alpha',
            result: 'succeeded',
            source: 'e2e',
            actorPublicId: $adminPublicId,
            targetType: 'user',
            targetPublicId: $adminPublicId,
            teamPublicId: $teamPublicId,
            correlationId: 'e2e-alpha',
            security: true,
            securityCategory: SecurityAuditCategory::Authentication,
        ));
        $recorder->record(new AuditEvent(
            module: 'shared',
            action: 'e2e.audit.beta',
            result: 'failed',
            source: 'admin-ui',
            actorPublicId: $adminPublicId,
            targetType: 'table_view',
            targetPublicId: (string) Str::ulid(),
            teamPublicId: $teamPublicId,
            correlationId: 'e2e-beta',
            security: false,
        ));
    }
}
