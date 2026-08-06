<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use DateTimeImmutable;

final readonly class InactivityDecision
{
    public function __construct(
        public bool $workEnded,
        public DateTimeImmutable $warningStartsAt,
        public DateTimeImmutable $warningEndsAt,
    ) {}
}
