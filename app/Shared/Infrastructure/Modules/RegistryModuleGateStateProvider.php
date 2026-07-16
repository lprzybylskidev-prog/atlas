<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Modules;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Contracts\ModuleGateStateProvider;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleAccessState;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistryModuleGateStateProvider implements ModuleGateStateProvider
{
    public function __construct(
        private ModuleRegistry $registry,
        private EffectivePermissionChecker $permissions,
        private ConnectionInterface $database,
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
            requiredDependenciesSatisfied: $deployed,
            technicallyAvailable: $effectiveState->technicallyAvailable,
            globallyActive: $effectiveState->globallyEnabled,
            teamActive: $effectiveState->teamEnabled,
            activeTeamValid: $activeTeamValid,
            permissionGranted: $this->permissionIsGranted($request, $activeTeamValid),
        );
    }

    private function activeTeamIsValid(ModuleAccessRequest $request): bool
    {
        if ($request->activeTeamId === null && $request->activeTeamPublicId === null) {
            return true;
        }

        $query = $this->database->table('teams')->where('is_active', true);

        if ($request->activeTeamId !== null) {
            $query->where('id', $request->activeTeamId);
        }

        if ($request->activeTeamPublicId !== null) {
            $query->where('public_id', $request->activeTeamPublicId);
        }

        return $query->exists();
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

        $publicId = $this->database->table('teams')
            ->where('id', $teamId)
            ->value('public_id');

        return is_string($publicId) ? $publicId : null;
    }

    private function teamId(?string $teamPublicId): ?int
    {
        if ($teamPublicId === null) {
            return null;
        }

        $id = $this->database->table('teams')
            ->where('public_id', $teamPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
