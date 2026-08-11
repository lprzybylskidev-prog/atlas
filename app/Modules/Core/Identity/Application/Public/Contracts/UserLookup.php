<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\UserDisplaySummary;
use App\Modules\Core\Identity\Application\Public\DTOs\UserNotificationContact;

interface UserLookup
{
    public function internalIdForPublicId(string $userPublicId): ?int;

    public function publicIdForInternalId(int $userId): ?string;

    public function publicIdForEmail(string $email): ?string;

    /**
     * @param  list<string>  $userPublicIds
     * @return array<string, int>
     */
    public function internalIdsForPublicIds(array $userPublicIds): array;

    public function notificationContactForInternalId(int $userId): ?UserNotificationContact;

    /**
     * @param  list<string>  $userPublicIds
     * @return array<string, UserDisplaySummary>
     */
    public function displaySummariesForPublicIds(array $userPublicIds): array;

    /**
     * @param  list<int>  $userIds
     * @return array<int, UserDisplaySummary>
     */
    public function displaySummariesForInternalIds(array $userIds): array;

    /**
     * @return list<UserDisplaySummary>
     */
    public function allDisplaySummaries(): array;

    /**
     * @return list<UserDisplaySummary>
     */
    public function allActiveDisplaySummaries(): array;
}
