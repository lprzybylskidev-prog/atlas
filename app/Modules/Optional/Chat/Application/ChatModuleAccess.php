<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Exceptions\ChatAccessDenied;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;

final readonly class ChatModuleAccess
{
    public function __construct(private ModuleGate $moduleGate) {}

    public function inspect(string $userPublicId, string $activeTeamPublicId, string $permission): ModuleAccessDecision
    {
        return $this->moduleGate->inspect(new ModuleAccessRequest(
            moduleKey: 'chat',
            activeTeamPublicId: $activeTeamPublicId,
            userPublicId: $userPublicId,
            requiredPermission: $permission,
        ));
    }

    public function allows(string $userPublicId, string $activeTeamPublicId, string $permission): bool
    {
        return $this->inspect($userPublicId, $activeTeamPublicId, $permission)->allowed;
    }

    public function ensureAllowed(string $userPublicId, string $activeTeamPublicId, string $permission): void
    {
        $decision = $this->inspect($userPublicId, $activeTeamPublicId, $permission);

        if (! $decision->allowed) {
            $reason = $decision->denialReason;

            if ($reason === null) {
                throw ChatAccessDenied::forReason('unknown');
            }

            throw ChatAccessDenied::forReason($reason->value);
        }
    }
}
