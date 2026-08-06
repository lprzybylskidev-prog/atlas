<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

use InvalidArgumentException;

final readonly class OfflineReconciliationPolicy
{
    public function __construct(
        public int $inactivityThresholdSeconds,
        public int $maximumOfflineGapSeconds,
        public int $clockSkewToleranceSeconds = 30,
    ) {
        if ($inactivityThresholdSeconds <= 0 || $maximumOfflineGapSeconds <= 0 || $clockSkewToleranceSeconds < 0) {
            throw new InvalidArgumentException('Offline reconciliation policy durations must be positive, with non-negative clock skew tolerance.');
        }

        if ($maximumOfflineGapSeconds < $inactivityThresholdSeconds) {
            throw new InvalidArgumentException('Maximum offline gap must be greater than or equal to the inactivity threshold.');
        }
    }
}
