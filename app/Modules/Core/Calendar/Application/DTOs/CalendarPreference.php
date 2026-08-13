<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\DTOs;

final readonly class CalendarPreference
{
    public function __construct(
        public int $defaultReminderMinutes = 15,
        public bool $emailEnabled = true,
    ) {}
}
