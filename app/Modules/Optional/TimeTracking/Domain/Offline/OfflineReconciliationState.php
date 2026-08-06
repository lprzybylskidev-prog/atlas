<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class OfflineReconciliationState
{
    public function __construct(
        public string $activeWorkSessionPublicId,
        public string $activeDeviceLeaseId,
        public int $lastAcceptedSequence,
        public DateTimeImmutable $receivedAt,
        public DateTimeImmutable $sessionExpiresAt,
    ) {
        if (trim($activeWorkSessionPublicId) === '' || trim($activeDeviceLeaseId) === '') {
            throw new InvalidArgumentException('Offline reconciliation state requires active work session and device lease identifiers.');
        }

        if ($lastAcceptedSequence < 0) {
            throw new InvalidArgumentException('Last accepted offline reconciliation sequence cannot be negative.');
        }
    }
}
