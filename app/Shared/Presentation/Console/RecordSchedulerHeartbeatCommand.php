<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Console\Command;
use Throwable;

final class RecordSchedulerHeartbeatCommand extends Command
{
    protected $signature = 'system:scheduler-heartbeat';

    protected $description = 'Record the scheduler heartbeat used by operational health checks.';

    public function handle(SchedulerHeartbeatMonitor $heartbeat): int
    {
        $startedAt = microtime(true);
        $heartbeat->markRunning();

        try {
            $heartbeat->markHealthy((int) round((microtime(true) - $startedAt) * 1000));
        } catch (Throwable $throwable) {
            $heartbeat->markFailed($throwable, (int) round((microtime(true) - $startedAt) * 1000));

            throw $throwable;
        }

        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
