<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Optional\TimeTracking\Application\Contracts\MaintenanceWindowStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\MaintenanceWindow;
use App\Modules\Optional\TimeTracking\Application\Enums\MaintenanceKind;
use DateTimeImmutable;

final readonly class MaintenanceCoordinator
{
    public function __construct(
        private MaintenanceWindowStore $maintenance,
        private TimeTrackingAudit $audit,
        private TimeTrackingLiveStatusPublisher $liveStatus,
    ) {}

    public function schedule(DateTimeImmutable $scheduledStartAt, string $reason): MaintenanceWindow
    {
        $window = $this->maintenance->schedule(MaintenanceKind::Scheduled, $scheduledStartAt, $reason);
        $this->audit->record(
            action: 'time_tracking.maintenance_scheduled',
            aggregateType: 'time_tracking_maintenance_window',
            aggregatePublicId: $window->publicId,
            reason: $reason,
            after: ['scheduled_start_at' => $scheduledStartAt->format(DateTimeImmutable::ATOM)],
        );

        return $window;
    }

    public function startEmergency(DateTimeImmutable $startedAt, string $reason): MaintenanceWindow
    {
        $window = $this->maintenance->startNow(MaintenanceKind::Emergency, $startedAt, $reason);
        $this->audit->record(
            action: 'time_tracking.maintenance_emergency_started',
            aggregateType: 'time_tracking_maintenance_window',
            aggregatePublicId: $window->publicId,
            reason: $reason,
            after: ['started_at' => $startedAt->format(DateTimeImmutable::ATOM)],
        );

        return $window;
    }

    public function activateScheduled(int $maintenanceWindowId, DateTimeImmutable $startedAt): void
    {
        $this->maintenance->activate($maintenanceWindowId, $startedAt);
        $this->audit->record(
            action: 'time_tracking.maintenance_scheduled_started',
            after: ['started_at' => $startedAt->format(DateTimeImmutable::ATOM)],
        );
    }

    public function complete(int $maintenanceWindowId, DateTimeImmutable $completedAt): void
    {
        $this->maintenance->complete($maintenanceWindowId, $completedAt);
        $this->audit->record(
            action: 'time_tracking.maintenance_completed',
            after: ['completed_at' => $completedAt->format(DateTimeImmutable::ATOM)],
        );
    }

    public function recordReturn(int $userId, DateTimeImmutable $returnedAt): bool
    {
        $recorded = $this->maintenance->recordReturn($userId, $returnedAt);

        if ($recorded) {
            $this->audit->record(
                action: 'time_tracking.maintenance_return_recorded',
                actorUserId: $userId,
                targetUserId: $userId,
                after: ['returned_at' => $returnedAt->format(DateTimeImmutable::ATOM)],
            );
            $this->liveStatus->publish('working', $userId, null, $returnedAt, [
                'type' => 'maintenance',
                'context' => 'Maintenance return',
            ]);
        }

        return $recorded;
    }
}
