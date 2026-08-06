<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class OfflineReconciliationRequest
{
    public function __construct(
        public string $workSessionPublicId,
        public string $deviceLeaseId,
        public int $sequence,
        public DateTimeImmutable $serverAnchorAt,
        public int $monotonicElapsedSeconds,
    ) {
        if (trim($workSessionPublicId) === '' || trim($deviceLeaseId) === '') {
            throw new InvalidArgumentException('Offline reconciliation requires work session and device lease identifiers.');
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException('Offline reconciliation sequence must be positive.');
        }

        if ($monotonicElapsedSeconds < 0) {
            throw new InvalidArgumentException('Offline reconciliation monotonic elapsed seconds cannot be negative.');
        }
    }
}
