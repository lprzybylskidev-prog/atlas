<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use InvalidArgumentException;

final readonly class InactivityPolicy
{
    public function __construct(
        public int $inactivityThresholdSeconds = 300,
        public int $warningSeconds = 30,
    ) {
        if ($inactivityThresholdSeconds < 1 || $warningSeconds < 1) {
            throw new InvalidArgumentException('Inactivity threshold and warning seconds must be positive.');
        }
    }
}
