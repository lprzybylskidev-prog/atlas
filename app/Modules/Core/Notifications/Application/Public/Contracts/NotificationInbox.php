<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationSummary;

interface NotificationInbox
{
    /**
     * @return list<NotificationSummary>
     */
    public function latestForUser(string $userPublicId, ?string $teamPublicId, int $limit): array;

    /**
     * @return list<NotificationSummary>
     */
    public function allForUser(string $userPublicId, ?string $teamPublicId): array;

    public function unreadCount(string $userPublicId, ?string $teamPublicId): int;

    public function markRead(string $userPublicId, string $notificationPublicId): void;
}
