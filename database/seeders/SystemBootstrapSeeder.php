<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Public\Contracts\AuthorizationBootstrapper;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Database\Seeder;

class SystemBootstrapSeeder extends Seeder
{
    public const ADMINISTRATION_TEAM_NAME = 'Administration';

    public function run(): void
    {
        app(AuthorizationBootstrapper::class)->synchronizeTechnicalFoundation();

        $team = app(BootstrapTeamProvider::class)->provide(self::ADMINISTRATION_TEAM_NAME);
        $teamId = app(TeamLookup::class)->internalIdForPublicId($team->publicId);

        if ($teamId === null) {
            return;
        }

        $this->activateModulesForAdministrationTeam($teamId);
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
