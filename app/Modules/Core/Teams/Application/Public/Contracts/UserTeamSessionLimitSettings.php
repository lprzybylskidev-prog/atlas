<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

interface UserTeamSessionLimitSettings
{
    /**
     * @return array{inactivityTimeoutMinutes: int, sessionMaxLifetimeMinutes: int, source: string}
     */
    public function resolvedForTeam(string $teamPublicId): array;

    public function setTeamOverrides(string $teamPublicId, ?int $inactivityTimeoutMinutes, ?int $sessionMaxLifetimeMinutes): void;

    /**
     * @return array{inactivityTimeoutMinutes: int, sessionMaxLifetimeMinutes: int, source: string}
     */
    public function resolvedForUserTeam(string $userPublicId, string $teamPublicId): array;

    public function setUserTeamOverrides(
        string $userPublicId,
        string $teamPublicId,
        ?int $inactivityTimeoutMinutes,
        ?int $sessionMaxLifetimeMinutes,
    ): void;
}
