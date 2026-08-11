<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Console\Command;

final class SchedulerStatusCommand extends Command
{
    protected $signature = 'system:scheduler-status';

    protected $description = 'Fail unless the managed scheduler has recorded a fresh successful heartbeat.';

    public function handle(SchedulerHeartbeatMonitor $heartbeat): int
    {
        $status = $heartbeat->status();

        if (($status['status'] ?? null) !== 'healthy' || ($status['isFresh'] ?? false) !== true) {
            $this->error('Scheduler heartbeat is not healthy and fresh.');

            return self::FAILURE;
        }

        $this->info('Scheduler heartbeat is healthy and fresh.');

        return self::SUCCESS;
    }
}
