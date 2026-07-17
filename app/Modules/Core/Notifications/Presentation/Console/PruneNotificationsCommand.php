<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Console;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationMaintenance;
use Illuminate\Console\Command;

final class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune
        {--read-days=90 : Retention window for read notification recipients}
        {--realtime-hours=72 : Retention window for realtime event buffer}';

    protected $description = 'Prune old read notifications and realtime event buffer records.';

    public function handle(NotificationMaintenance $maintenance): int
    {
        $result = $maintenance->prune(
            readRetentionDays: max(1, (int) $this->option('read-days')),
            realtimeRetentionHours: max(1, (int) $this->option('realtime-hours')),
        );

        $this->info(sprintf(
            'Pruned %d recipient(s), %d notification(s), and %d realtime event(s).',
            $result->deletedRecipients,
            $result->deletedNotifications,
            $result->deletedRealtimeEvents,
        ));

        return self::SUCCESS;
    }
}
