<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Presentation\Console;

use App\Modules\Core\Calendar\Application\Services\CalendarReminderDispatcher;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class DispatchCalendarRemindersCommand extends Command
{
    protected $signature = 'calendar:dispatch-reminders';

    protected $description = 'Dispatch due personal Calendar reminders.';

    public function handle(CalendarReminderDispatcher $dispatcher): int
    {
        $this->info(sprintf('Dispatched %d Calendar reminder(s).', $dispatcher->dispatch(new DateTimeImmutable)));

        return self::SUCCESS;
    }
}
