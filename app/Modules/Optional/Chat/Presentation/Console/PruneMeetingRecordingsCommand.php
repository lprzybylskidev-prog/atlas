<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Console;

use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use Illuminate\Console\Command;

final class PruneMeetingRecordingsCommand extends Command
{
    protected $signature = 'chat:meetings:prune-recordings';

    protected $description = 'Apply the configured Meeting recording retention policy.';

    public function handle(MeetingRecordingRetention $retention): int
    {
        $result = $retention->cleanup();
        $this->info($result['disabled'] ? 'Meeting recording retention is disabled.' : sprintf('Meeting recordings: %d removed, %d failed.', $result['removed'], $result['failed']));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
