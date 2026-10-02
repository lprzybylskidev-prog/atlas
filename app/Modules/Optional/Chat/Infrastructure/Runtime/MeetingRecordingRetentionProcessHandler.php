<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Runtime;

use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetentionProcess;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessHandler;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessReporter;

final readonly class MeetingRecordingRetentionProcessHandler implements ManagedProcessHandler
{
    public function __construct(private MeetingRecordingRetention $retention, private ManagedProcessReporter $reporter) {}

    public function processKey(): string
    {
        return MeetingRecordingRetentionProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $removed = 0;
        $failed = 0;
        $batches = 0;
        $this->reporter->running($runPublicId, 'cleanup', 0, null, 'Cleaning expired Meeting recordings');

        do {
            $result = $this->retention->cleanup();
            $removed += $result['removed'];
            $failed += $result['failed'];
            $batches++;
            $this->reporter->running($runPublicId, 'cleanup', $removed, null, 'Cleaning expired Meeting recordings', [
                'removed' => $removed,
                'failed' => $failed,
            ]);
        } while (! $result['disabled'] && $result['removed'] > 0);

        $this->reporter->succeeded(
            $runPublicId,
            'completed',
            $removed,
            $removed,
            'Meeting recording retention completed',
            ['removed' => $removed, 'failed' => $failed],
            ['removed' => $removed, 'failed' => $failed, 'batches' => $batches, 'disabled' => $result['disabled']],
        );
    }
}
