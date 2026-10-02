<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Console;

use App\Modules\Optional\Chat\Application\MeetingReminderDispatcher;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class DispatchMeetingRemindersCommand extends Command
{
    protected $signature = 'chat:meetings:dispatch-reminders';

    protected $description = 'Dispatch due Meeting reminders.';

    public function handle(MeetingReminderDispatcher $dispatcher): int
    {
        $this->info(sprintf('Dispatched %d Meeting reminder(s).', $dispatcher->dispatch(new DateTimeImmutable)));

        return self::SUCCESS;
    }
}
