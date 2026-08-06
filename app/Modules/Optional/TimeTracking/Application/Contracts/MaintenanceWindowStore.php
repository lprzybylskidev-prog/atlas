<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\MaintenanceWindow;
use App\Modules\Optional\TimeTracking\Application\Enums\MaintenanceKind;
use DateTimeImmutable;

interface MaintenanceWindowStore
{
    public function schedule(MaintenanceKind $kind, DateTimeImmutable $scheduledStartAt, string $reason): MaintenanceWindow;

    public function startNow(MaintenanceKind $kind, DateTimeImmutable $startedAt, string $reason): MaintenanceWindow;

    public function activate(int $maintenanceWindowId, DateTimeImmutable $startedAt): void;

    public function complete(int $maintenanceWindowId, DateTimeImmutable $completedAt): void;

    public function recordReturn(int $userId, DateTimeImmutable $returnedAt): bool;
}
