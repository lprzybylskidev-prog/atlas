<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExactTimeChange
{
    public function __construct(
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public ?int $exactSeconds,
    ) {
        if ($exactSeconds !== null && $exactSeconds < 0) {
            throw new InvalidArgumentException('Exact time change seconds cannot be negative.');
        }
    }
}
