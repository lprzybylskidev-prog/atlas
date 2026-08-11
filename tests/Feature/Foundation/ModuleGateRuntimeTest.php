<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\Contracts\ModuleTechnicalAvailability;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use Database\Seeders\E2eVisibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModuleGateRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_module_gate_uses_registry_active_team_and_permission_state(): void
    {
        $this->seed(E2eVisibilitySeeder::class);

        $gate = app(ModuleGate::class);
        $admin = DB::table(IdentityDatabaseTable::USERS)->where('email', E2eVisibilitySeeder::ADMIN_EMAIL)->first();
        $limited = DB::table(IdentityDatabaseTable::USERS)->where('email', E2eVisibilitySeeder::LIMITED_EMAIL)->first();
        $team = DB::table(TeamsDatabaseTable::TEAMS)->where('name', E2eVisibilitySeeder::TEAM_NAME)->first();

        self::assertIsObject($admin);
        self::assertIsObject($limited);
        self::assertIsObject($team);
        self::assertIsString($admin->public_id);
        self::assertIsString($limited->public_id);
        self::assertIsString($team->public_id);
        self::assertSame('sensitive', $admin->account_sensitivity);

        $allowed = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamPublicId: $team->public_id,
            userPublicId: $admin->public_id,
            requiredPermission: 'admin.system-status',
        ));

        $missingPermission = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamPublicId: $team->public_id,
            userPublicId: $limited->public_id,
            requiredPermission: 'admin.system-status',
        ));

        $notDeployed = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'not_deployed_test_module',
            activeTeamPublicId: $team->public_id,
            userPublicId: $admin->public_id,
            requiredPermission: 'admin.system-status',
        ));

        self::assertTrue($allowed->allowed);
        self::assertFalse($missingPermission->allowed);
        self::assertSame(ModuleAccessDenialReason::PermissionDenied, $missingPermission->denialReason);
        self::assertFalse($notDeployed->allowed);
        self::assertSame(ModuleAccessDenialReason::NotDeployed, $notDeployed->denialReason);
    }

    public function test_runtime_module_gate_denies_when_required_dependency_is_inactive(): void
    {
        $this->seed(E2eVisibilitySeeder::class);

        $activation = app(ModuleActivationService::class);
        $gate = app(ModuleGate::class);
        $team = DB::table(TeamsDatabaseTable::TEAMS)->where('name', E2eVisibilitySeeder::TEAM_NAME)->first();

        self::assertIsObject($team);
        self::assertIsString($team->public_id);

        $activation->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Global,
            enabled: false,
            reason: 'Prepare a dependency-safe inactive dependency fixture.',
        ));
        $activation->change(new ModuleActivationChange(
            moduleKey: 'feature_flags',
            scope: ModuleActivationScope::Global,
            enabled: false,
            reason: 'Disable required dependency for ModuleGate dependency-state test.',
        ));
        $activation->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Restore target to verify dependency-state denial.',
        ));

        $decision = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'time_tracking',
            activeTeamPublicId: $team->public_id,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(ModuleAccessDenialReason::MissingRequiredDependency, $decision->denialReason);
    }

    public function test_runtime_module_gate_reaches_target_activation_after_dependencies_are_active(): void
    {
        $this->seed(E2eVisibilitySeeder::class);

        $activation = app(ModuleActivationService::class);
        $team = DB::table(TeamsDatabaseTable::TEAMS)->where('name', E2eVisibilitySeeder::TEAM_NAME)->first();

        self::assertIsObject($team);
        self::assertIsString($team->public_id);

        foreach (['feature_flags', 'managed_processes', 'reports'] as $moduleKey) {
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'Enable required dependency for ModuleGate dependency-state test.',
            ));
        }

        $activation->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Global,
            enabled: false,
            reason: 'Disable target module for ModuleGate dependency-state test.',
        ));

        $decision = app(ModuleGate::class)->inspect(new ModuleAccessRequest(
            moduleKey: 'time_tracking',
            activeTeamPublicId: $team->public_id,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(ModuleAccessDenialReason::GloballyInactive, $decision->denialReason);
    }

    public function test_runtime_module_gate_denies_when_required_dependency_is_technically_unavailable(): void
    {
        $this->seed(E2eVisibilitySeeder::class);

        $activation = app(ModuleActivationService::class);
        $team = DB::table(TeamsDatabaseTable::TEAMS)->where('name', E2eVisibilitySeeder::TEAM_NAME)->first();

        self::assertIsObject($team);
        self::assertIsString($team->public_id);

        foreach (['feature_flags', 'managed_processes', 'reports', 'time_tracking'] as $moduleKey) {
            $activation->change(new ModuleActivationChange(
                moduleKey: $moduleKey,
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'Enable modules for ModuleGate technical availability test.',
            ));
        }

        $this->app->bind(ModuleTechnicalAvailability::class, static fn (): ModuleTechnicalAvailability => new class implements ModuleTechnicalAvailability
        {
            public function available(ModuleDefinition $module): bool
            {
                return $module->key()->value !== 'feature_flags';
            }
        });
        app(ModuleActivationService::class)->invalidate('feature_flags');

        $decision = app(ModuleGate::class)->inspect(new ModuleAccessRequest(
            moduleKey: 'time_tracking',
            activeTeamPublicId: $team->public_id,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(ModuleAccessDenialReason::MissingRequiredDependency, $decision->denialReason);
    }
}
