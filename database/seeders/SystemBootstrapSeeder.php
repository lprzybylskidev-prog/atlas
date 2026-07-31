<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemBootstrapSeeder extends Seeder
{
    public const ADMINISTRATION_TEAM_NAME = 'Administration';

    public function run(): void
    {
        app(InstallStarterRoles::class)->handle();
        $this->synchronizeAdministratorRole();

        $team = app(BootstrapTeamProvider::class)->provide(self::ADMINISTRATION_TEAM_NAME);
        $teamId = DB::table(DatabaseTable::TEAMS)
            ->where('public_id', $team->publicId)
            ->value('id');

        if (! is_numeric($teamId)) {
            return;
        }

        $this->activateModulesForAdministrationTeam((int) $teamId);
    }

    private function synchronizeAdministratorRole(): void
    {
        $store = app(PermissionRoleStore::class);
        $permissions = app(PermissionCatalogRegistry::class)->names();
        $existing = $store->rolePermissionNames(StarterRoleName::Administrator->value);
        $missing = array_values(array_diff($permissions, $existing));

        if ($missing === []) {
            return;
        }

        $store->grantPermissionsToRole(StarterRoleName::Administrator->value, $missing);
    }

    private function activateModulesForAdministrationTeam(int $teamId): void
    {
        $activation = app(ModuleActivationService::class);

        foreach (app(ModuleRegistry::class)->all() as $module) {
            if ($module->category() === ModuleCategory::Application) {
                continue;
            }

            $moduleKey = $module->key()->value;

            if ($module->supportsGlobalActivation() && ! $activation->effectiveState($moduleKey)->globallyEnabled) {
                $activation->change(new ModuleActivationChange(
                    moduleKey: $moduleKey,
                    scope: ModuleActivationScope::Global,
                    enabled: true,
                    reason: 'System bootstrap grants Administration access to deployed modules.',
                    source: ModuleActivationSource::System,
                ));
            }

            $state = $activation->effectiveState($moduleKey, $teamId);

            if ($module->supportsTeamActivation() && ! $state->teamEnabled) {
                $activation->change(new ModuleActivationChange(
                    moduleKey: $moduleKey,
                    scope: ModuleActivationScope::Team,
                    enabled: true,
                    reason: 'System bootstrap grants Administration access to deployed modules.',
                    teamId: $teamId,
                    source: ModuleActivationSource::System,
                ));
            }
        }
    }
}
