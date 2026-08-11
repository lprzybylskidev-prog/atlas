<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\Contracts;

use App\Shared\Application\Authorization\DTOs\UserTeamAuthorizationAssignments;

interface UserTeamAuthorizationManager
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function roleOptions(): array;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function permissionOptions(): array;

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array;

    public function assignmentsForUserTeam(string $userPublicId, string $teamPublicId): UserTeamAuthorizationAssignments;

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     * @param  array<string, mixed>|null  $presetSnapshot
     * @param  array<string, int|null>  $resultingLimits
     */
    public function replaceAssignmentsForUserTeam(
        string $actorPublicId,
        string $userPublicId,
        string $teamPublicId,
        array $roleNames,
        array $directPermissionNames,
        ?string $reason = null,
        string $sourceType = 'manual',
        ?string $sourcePublicId = null,
        ?string $sourceDisplayNameSnapshot = null,
        ?string $copiedFromUserPublicId = null,
        ?int $presetVersion = null,
        ?array $presetSnapshot = null,
        array $resultingLimits = [],
        ?int $expectedVersion = null,
    ): void;
}
