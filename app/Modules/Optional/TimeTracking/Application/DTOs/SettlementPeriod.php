<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use DateTimeImmutable;

final readonly class SettlementPeriod
{
    public function __construct(
        public int $id,
        public string $publicId,
        public DateTimeImmutable $startsOn,
        public DateTimeImmutable $endsOn,
        public string $status,
    ) {}
}
