<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

interface CalendarReminderStore
{
    public function claim(string $eventPublicId, string $occurrenceDate, int $minutesBefore): bool;

    public function delivered(string $eventPublicId, string $occurrenceDate, int $minutesBefore): void;
}
