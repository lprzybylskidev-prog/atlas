<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use InvalidArgumentException;

final readonly class TimeTrackingDeactivationState
{
    public function __construct(
        public int $activeWorkSessions = 0,
        public int $activeBreaks = 0,
        public int $activeOtherWork = 0,
        public int $activeMaintenanceWindows = 0,
        public int $pendingCorrections = 0,
        public int $unsafeReportJobs = 0,
    ) {
        foreach ([
            $activeWorkSessions,
            $activeBreaks,
            $activeOtherWork,
            $activeMaintenanceWindows,
            $pendingCorrections,
            $unsafeReportJobs,
        ] as $count) {
            if ($count < 0) {
                throw new InvalidArgumentException('TimeTracking deactivation readiness counts cannot be negative.');
            }
        }
    }

    public function hasBlockers(): bool
    {
        return $this->activeWorkSessions > 0
            || $this->activeBreaks > 0
            || $this->activeOtherWork > 0
            || $this->activeMaintenanceWindows > 0
            || $this->pendingCorrections > 0
            || $this->unsafeReportJobs > 0;
    }

    public function primaryBlockerType(): string
    {
        if ($this->activeWorkSessions > 0) {
            return 'time_tracking.active_work';
        }

        if ($this->activeBreaks > 0) {
            return 'time_tracking.active_break';
        }

        if ($this->activeOtherWork > 0) {
            return 'time_tracking.active_other_work';
        }

        if ($this->activeMaintenanceWindows > 0) {
            return 'time_tracking.active_maintenance';
        }

        if ($this->pendingCorrections > 0) {
            return 'time_tracking.pending_corrections';
        }

        if ($this->unsafeReportJobs > 0) {
            return 'time_tracking.unsafe_report_jobs';
        }

        return 'time_tracking.none';
    }

    public function blockerSummary(): string
    {
        return sprintf(
            'TimeTracking has active work: %d, breaks: %d, other work: %d, maintenance: %d, pending corrections: %d, unsafe report jobs: %d.',
            $this->activeWorkSessions,
            $this->activeBreaks,
            $this->activeOtherWork,
            $this->activeMaintenanceWindows,
            $this->pendingCorrections,
            $this->unsafeReportJobs,
        );
    }
}
