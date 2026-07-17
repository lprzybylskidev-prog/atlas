<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\DTOs;

final readonly class NotificationCleanupResult
{
    public function __construct(
        public int $deletedRecipients,
        public int $deletedNotifications,
        public int $deletedRealtimeEvents,
    ) {}
}
