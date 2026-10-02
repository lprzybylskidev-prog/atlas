<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Console;

use App\Modules\Optional\Chat\Application\MeetingRtcMaintenance;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class EndExpiredEmptyMeetingRtcSessionsCommand extends Command
{
    protected $signature = 'chat:meetings:end-empty-rtc-sessions';

    protected $description = 'End only RTC resources for Meeting rooms empty for 15 continuous minutes.';

    public function handle(MeetingRtcMaintenance $maintenance): int
    {
        $this->info(sprintf('Ended %d empty Meeting RTC session(s).', $maintenance->endExpiredEmptySessions(new DateTimeImmutable('now', new DateTimeZone('UTC')))));

        return self::SUCCESS;
    }
}
