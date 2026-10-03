<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Runtime;

use App\Modules\Optional\Chat\Application\ChatRetention;
use App\Modules\Optional\Chat\Application\ChatRetentionProcess;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessHandler;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessReporter;

final readonly class ChatRetentionProcessHandler implements ManagedProcessHandler
{
    public function __construct(private ChatRetention $retention, private ManagedProcessReporter $reporter) {}

    public function processKey(): string
    {
        return ChatRetentionProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $totals = ['removed' => 0, 'attachmentsRemoved' => 0, 'timelineEntriesRemoved' => 0, 'failed' => 0];
        $this->reporter->running($runPublicId, 'cleanup', 0, null, 'Cleaning expired Chat content');
        do {
            $result = $this->retention->cleanup();
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $result[$key];
            }
            $this->reporter->running($runPublicId, 'cleanup', $totals['removed'], null, 'Cleaning expired Chat content', $totals);
        } while (! $result['disabled'] && $result['removed'] > 0);
        $this->reporter->succeeded($runPublicId, 'completed', $totals['removed'], $totals['removed'], 'Chat retention completed', $totals, $totals + ['disabled' => $result['disabled']]);
    }
}
