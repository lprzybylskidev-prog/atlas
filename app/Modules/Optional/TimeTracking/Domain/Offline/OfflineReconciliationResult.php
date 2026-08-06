<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

use DateTimeImmutable;

final readonly class OfflineReconciliationResult
{
    public function __construct(
        public OfflineReconciliationDecision $decision,
        public int $acceptedSeconds,
        public ?DateTimeImmutable $countedEndsAt,
    ) {}

    public function accepted(): bool
    {
        return in_array($this->decision, [
            OfflineReconciliationDecision::AcceptedActive,
            OfflineReconciliationDecision::AcceptedEndedByInactivity,
        ], true);
    }
}
