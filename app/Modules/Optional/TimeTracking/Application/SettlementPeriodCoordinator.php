<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\SettlementPeriodStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\SettlementPeriod;
use DateTimeImmutable;

final readonly class SettlementPeriodCoordinator
{
    public function __construct(private SettlementPeriodStore $periods) {}

    public function setStartDay(int $day): void
    {
        $this->periods->setStartDay($day);
    }

    public function periodFor(DateTimeImmutable $date): SettlementPeriod
    {
        return $this->periods->periodFor($date);
    }

    public function closeDuePeriods(DateTimeImmutable $now): int
    {
        return $this->periods->closeDuePeriods($now);
    }
}
