<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use InvalidArgumentException;

final readonly class BreakPolicy
{
    public function __construct(
        public int $dailyLimitSeconds,
        public int $maximumSingleBreakSeconds,
        public int $warningBeforeMaximumSeconds,
        public string $source,
    ) {
        if (
            $dailyLimitSeconds < 0
            || $maximumSingleBreakSeconds <= 0
            || $warningBeforeMaximumSeconds < 0
            || $warningBeforeMaximumSeconds >= $maximumSingleBreakSeconds
            || trim($source) === ''
        ) {
            throw new InvalidArgumentException('Break policy limits and source must be valid.');
        }
    }
}
