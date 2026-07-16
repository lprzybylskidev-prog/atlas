<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Activation\Contracts;

use App\Shared\Application\Modules\Activation\EffectiveModuleState;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use Carbon\CarbonImmutable;

interface ModuleActivationService
{
    public function effectiveState(string $moduleKey, ?int $teamId = null): EffectiveModuleState;

    public function change(ModuleActivationChange $change): EffectiveModuleState;

    public function schedule(ModuleActivationChange $change, CarbonImmutable $effectiveAt): string;

    public function cancelSchedule(string $schedulePublicId, int $actorUserId, string $reason): void;

    public function clearTeamOverride(string $moduleKey, int $teamId, int $actorUserId, string $reason): EffectiveModuleState;

    public function applyDueSchedules(?CarbonImmutable $now = null): int;

    public function invalidate(string $moduleKey, ?int $teamId = null): void;
}
