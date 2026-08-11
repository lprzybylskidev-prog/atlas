<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Console;

use App\Shared\Presentation\Jobs\RuntimeQueueSmokeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RuntimeQueueSmokeCommand extends Command
{
    protected $signature = 'system:queue-smoke {--timeout=60 : Maximum seconds to wait for every queue}';

    protected $description = 'Dispatch and observe one harmless probe through every configured Horizon queue.';

    public function handle(): int
    {
        $queues = config('horizon.defaults.supervisor-1.queue');

        if (! is_array($queues) || $queues === []) {
            $this->error('No Horizon queues are configured.');

            return self::FAILURE;
        }

        $markers = [];

        foreach ($queues as $queue) {
            if (! is_string($queue) || $queue === '') {
                $this->error('Horizon contains an invalid queue name.');

                return self::FAILURE;
            }

            $marker = 'atlas:runtime-smoke:queue:'.Str::uuid()->toString();
            $markers[$queue] = $marker;
            RuntimeQueueSmokeJob::dispatch($marker)->onConnection('redis')->onQueue($queue);
        }

        $deadline = microtime(true) + max(1, (int) $this->option('timeout'));

        do {
            $pending = array_filter($markers, static fn (string $marker): bool => Cache::get($marker) !== true);

            if ($pending === []) {
                foreach ($markers as $marker) {
                    Cache::forget($marker);
                }

                $this->info('Every configured Horizon queue executed its probe.');

                return self::SUCCESS;
            }

            usleep(200_000);
        } while (microtime(true) < $deadline);

        $this->error('Queue probes timed out: '.implode(', ', array_keys($pending)));

        return self::FAILURE;
    }
}
