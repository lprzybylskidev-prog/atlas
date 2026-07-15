<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\Contracts\ModuleGateStateProvider;

final readonly class DefaultModuleGate implements ModuleGate
{
    public function __construct(private ModuleGateStateProvider $stateProvider) {}

    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
    {
        $state = $this->stateProvider->stateFor($request);

        if (! $state->deployed) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::NotDeployed);
        }

        if (! $state->requiredDependenciesSatisfied) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::MissingRequiredDependency);
        }

        if (! $state->technicallyAvailable) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::TechnicallyUnavailable);
        }

        if (! $state->globallyActive) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::GloballyInactive);
        }

        if (! $state->teamActive) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::TeamInactive);
        }

        if (! $state->activeTeamValid) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::InvalidActiveTeam);
        }

        if (! $state->permissionGranted) {
            return ModuleAccessDecision::deny(ModuleAccessDenialReason::PermissionDenied);
        }

        return ModuleAccessDecision::allow();
    }

    public function allows(ModuleAccessRequest $request): bool
    {
        return $this->inspect($request)->allowed;
    }
}
