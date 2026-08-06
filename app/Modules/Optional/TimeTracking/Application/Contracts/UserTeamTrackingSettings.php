<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

interface UserTeamTrackingSettings
{
    public function isEnabledForUserTeam(int $userId, int $teamId): bool;

    public function setEnabledForAssignment(int $teamUserAssignmentId, bool $enabled): void;
}
