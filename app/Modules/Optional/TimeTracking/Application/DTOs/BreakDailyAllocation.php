<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class BreakDailyAllocation
{
    public function __construct(
        public string $calendarDate,
        public int $breakSeconds,
        public int $countedBreakSeconds,
        public int $excessBreakSeconds,
        public int $remainingSeconds,
    ) {}
}
