<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Console;

use App\Modules\Optional\Chat\Application\MeetingRecordingFinalizer;
use Illuminate\Console\Command;

final class FinalizeMeetingRecordingsCommand extends Command
{
    protected $signature = 'chat:meetings:finalize-recordings {--limit=10}';

    protected $description = 'Finalize completed Meeting Egress segments and import recordings into Files.';

    public function handle(MeetingRecordingFinalizer $finalizer): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (! is_int($limit)) {
            $this->error('The --limit option must be an integer between 1 and 100.');

            return self::INVALID;
        }
        $result = $finalizer->finalizePending($limit);
        $this->info(sprintf('Meeting recordings: %d ready, %d failed, %d still processing.', $result['ready'], $result['failed'], $result['pending']));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
