<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\Contracts;

use App\Shared\Application\Teams\DTOs\TeamDisplaySummary;
use App\Shared\Application\Teams\DTOs\TeamLookupSummary;
use App\Shared\Application\Teams\DTOs\TeamUserAssignmentLookupSummary;

interface TeamLookup
{
    public function internalIdForPublicId(string $teamPublicId): ?int;

    public function publicIdForInternalId(int $teamId): ?string;

    public function activeInternalIdForPublicId(string $teamPublicId): ?int;

    public function activePublicIdForInternalId(int $teamId): ?string;

    public function hasActiveHeadManager(string $teamPublicId): bool;

    public function activeAssignmentInternalIdForUserTeam(int $userId, int $teamId): ?int;

    /**
     * @param  list<int>  $assignmentIds
     * @return array<int, TeamUserAssignmentLookupSummary>
     */
    public function assignmentSummariesForInternalIds(array $assignmentIds): array;

    /**
     * @return list<int>
     */
    public function allInternalIds(): array;

    /**
     * @return list<TeamLookupSummary>
     */
    public function allSummaries(): array;

    /**
     * @param  list<int>  $teamIds
     * @return array<int, TeamLookupSummary>
     */
    public function summariesForInternalIds(array $teamIds): array;

    /**
     * @param  list<string>  $teamPublicIds
     * @return list<int>
     */
    public function internalIdsForPublicIds(array $teamPublicIds): array;

    /**
     * @param  list<string>  $teamPublicIds
     * @return array<string, TeamDisplaySummary>
     */
    public function displaySummariesForPublicIds(array $teamPublicIds): array;
}
