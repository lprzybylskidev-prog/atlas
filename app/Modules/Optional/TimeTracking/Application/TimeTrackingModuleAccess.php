<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use RuntimeException;

final readonly class TimeTrackingModuleAccess
{
    private const MODULE_KEY = 'time_tracking';

    public function __construct(private ModuleGate $moduleGate) {}

    public function ensureAllowed(
        ?int $activeTeamId = null,
        ?string $activeTeamPublicId = null,
        ?string $userPublicId = null,
        ?string $requiredPermission = null,
    ): void {
        $decision = $this->inspect($activeTeamId, $activeTeamPublicId, $userPublicId, $requiredPermission);

        if (! $decision->allowed) {
            $reason = $decision->denialReason;

            throw new RuntimeException(sprintf('TimeTracking module access denied: %s.', $reason === null ? 'unknown' : $reason->value));
        }
    }

    public function allows(
        ?int $activeTeamId = null,
        ?string $activeTeamPublicId = null,
        ?string $userPublicId = null,
        ?string $requiredPermission = null,
    ): bool {
        return $this->inspect($activeTeamId, $activeTeamPublicId, $userPublicId, $requiredPermission)->allowed;
    }

    private function inspect(
        ?int $activeTeamId,
        ?string $activeTeamPublicId,
        ?string $userPublicId,
        ?string $requiredPermission,
    ): ModuleAccessDecision {
        $decision = $this->moduleGate->inspect(new ModuleAccessRequest(
            moduleKey: self::MODULE_KEY,
            activeTeamId: $activeTeamId,
            activeTeamPublicId: $activeTeamPublicId,
            userPublicId: $userPublicId,
            requiredPermission: $requiredPermission,
        ));

        return $decision;
    }
}
