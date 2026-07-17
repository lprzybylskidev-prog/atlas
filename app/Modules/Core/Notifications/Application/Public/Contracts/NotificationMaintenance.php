<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use App\Modules\Core\Notifications\Application\Public\DTOs\NotificationCleanupResult;

interface NotificationMaintenance
{
    public function prune(int $readRetentionDays, int $realtimeRetentionHours): NotificationCleanupResult;
}
