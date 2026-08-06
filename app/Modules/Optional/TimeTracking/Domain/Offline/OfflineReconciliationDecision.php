<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Offline;

enum OfflineReconciliationDecision: string
{
    case AcceptedActive = 'accepted_active';
    case AcceptedEndedByInactivity = 'accepted_ended_by_inactivity';
    case RejectedDuplicate = 'rejected_duplicate';
    case RejectedReordered = 'rejected_reordered';
    case RejectedExcessiveGap = 'rejected_excessive_gap';
    case RejectedExpiredSession = 'rejected_expired_session';
    case RejectedParallelWork = 'rejected_parallel_work';
    case RejectedClockAnomaly = 'rejected_clock_anomaly';
}
