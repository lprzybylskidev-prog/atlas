<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\BreakPolicy;

interface BreakPolicyStore
{
    public function policyForUserTeam(int $userId, int $teamId): BreakPolicy;

    public function setGlobalPolicy(int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void;

    public function setTeamPolicy(int $teamId, int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void;

    public function setUserTeamPolicy(int $teamUserAssignmentId, int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void;

    public function clearUserTeamPolicy(int $teamUserAssignmentId): void;
}
