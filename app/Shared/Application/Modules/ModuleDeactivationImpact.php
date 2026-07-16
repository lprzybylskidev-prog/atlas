<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

final readonly class ModuleDeactivationImpact
{
    /**
     * @param  list<string>  $dependentModules
     * @param  list<string>  $affectedTeamPublicIds
     * @param  list<string>  $scheduledChangePublicIds
     * @param  list<ModuleDeactivationBlocker>  $blockers
     * @param  list<ModuleDeactivationSafeAction>  $safeActions
     */
    public function __construct(
        public string $moduleKey,
        public string $scope,
        public array $dependentModules = [],
        public array $affectedTeamPublicIds = [],
        public array $scheduledChangePublicIds = [],
        public array $blockers = [],
        public array $safeActions = [],
    ) {}

    public function canDeactivate(): bool
    {
        return $this->dependentModules === [] && $this->blockers === [];
    }
}
