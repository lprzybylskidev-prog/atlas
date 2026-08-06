<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\Contracts;

interface UserBreakPolicySettings
{
    /**
     * @return array{dailyLimitMinutes: int, maximumSingleBreakMinutes: int, source: string}
     */
    public function resolvedForUserTeam(string $userPublicId, string $teamPublicId): array;

    public function isTrackedForUserTeam(string $userPublicId, string $teamPublicId): bool;

    /**
     * @return array{dailyLimitMinutes: int, maximumSingleBreakMinutes: int, source: string}
     */
    public function resolvedForTeam(string $teamPublicId): array;

    public function setTeamOverrides(string $teamPublicId, ?int $dailyLimitMinutes, ?int $maximumSingleBreakMinutes): void;

    public function setUserTeamOverrides(
        string $userPublicId,
        string $teamPublicId,
        ?int $dailyLimitMinutes,
        ?int $maximumSingleBreakMinutes,
    ): void;
}
