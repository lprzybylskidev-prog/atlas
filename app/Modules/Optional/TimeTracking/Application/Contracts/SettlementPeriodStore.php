<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\SettlementPeriod;
use DateTimeImmutable;

interface SettlementPeriodStore
{
    public function startDay(): int;

    public function setStartDay(int $day): void;

    public function periodFor(DateTimeImmutable $date): SettlementPeriod;

    public function closeDuePeriods(DateTimeImmutable $now): int;
}
