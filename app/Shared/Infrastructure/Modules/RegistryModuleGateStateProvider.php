<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Modules;

use App\Shared\Application\Authorization\Contracts\EffectivePermissionChecker;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Contracts\ModuleGateStateProvider;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleAccessState;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Teams\Contracts\TeamLookup;

final readonly class RegistryModuleGateStateProvider implements ModuleGateStateProvider
{
    public function __construct(
        private ModuleRegistry $registry,
        private EffectivePermissionChecker $permissions,
        private TeamLookup $teams,
        private ModuleActivationService $activation,
    ) {}

    public function stateFor(ModuleAccessRequest $request): ModuleAccessState
    {
        $moduleKey = new ModuleKey($request->moduleKey);
        $deployed = $this->registry->has($moduleKey);
        $activeTeamValid = $this->activeTeamIsValid($request);
        $activeTeamId = $request->activeTeamId ?? $this->teamId($request->activeTeamPublicId);
        $effectiveState = $this->activation->effectiveState($request->moduleKey, $activeTeamId);

        return new ModuleAccessState(
            deployed: $deployed,
            requiredDependenciesSatisfied: $this->requiredDependenciesAreSatisfied($moduleKey, $deployed, $activeTeamId),
            technicallyAvailable: $effectiveState->technicallyAvailable,
            globallyActive: $effectiveState->globallyEnabled,
            teamActive: $effectiveState->teamEnabled,
            activeTeamValid: $activeTeamValid,
            permissionGranted: $this->permissionIsGranted($request, $activeTeamValid),
        );
    }

    private function requiredDependenciesAreSatisfied(ModuleKey $moduleKey, bool $deployed, ?int $activeTeamId): bool
    {
        if (! $deployed) {
            return false;
        }

        foreach ($this->registry->get($moduleKey)->requiredDependencies() as $dependency) {
            $state = $this->activation->effectiveState($dependency->value, $activeTeamId);

            if (! $state->deployed || ! $state->technicallyAvailable || ! $state->globallyEnabled || ! $state->teamEnabled) {
                return false;
            }
        }

        return true;
    }

    private function activeTeamIsValid(ModuleAccessRequest $request): bool
    {
        if ($request->activeTeamId === null && $request->activeTeamPublicId === null) {
            return true;
        }

        if ($request->activeTeamId !== null && $request->activeTeamPublicId !== null) {
            return $this->teams->activePublicIdForInternalId($request->activeTeamId) === $request->activeTeamPublicId;
        }

        if ($request->activeTeamId !== null) {
            return $this->teams->activePublicIdForInternalId($request->activeTeamId) !== null;
        }

        return $this->teams->activeInternalIdForPublicId((string) $request->activeTeamPublicId) !== null;
    }

    private function permissionIsGranted(ModuleAccessRequest $request, bool $activeTeamValid): bool
    {
        if ($request->requiredPermission === null) {
            return true;
        }

        if (! $activeTeamValid || $request->userPublicId === null) {
            return false;
        }

        $teamPublicId = $request->activeTeamPublicId ?? $this->teamPublicId($request->activeTeamId);

        if ($teamPublicId === null) {
            return false;
        }

        return $this->permissions->check(new EffectivePermissionRequest(
            userPublicId: $request->userPublicId,
            permission: $request->requiredPermission,
            teamPublicId: $teamPublicId,
        ))->allowed;
    }

    private function teamPublicId(?int $teamId): ?string
    {
        if ($teamId === null) {
            return null;
        }

        return $this->teams->publicIdForInternalId($teamId);
    }

    private function teamId(?string $teamPublicId): ?int
    {
        if ($teamPublicId === null) {
            return null;
        }

        return $this->teams->internalIdForPublicId($teamPublicId);
    }
}
