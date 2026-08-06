<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\BreakSessionStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\MaintenanceWindowStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\MaintenanceWindow;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakClosureReason;
use App\Modules\Optional\TimeTracking\Application\Enums\MaintenanceKind;
use App\Modules\Optional\TimeTracking\Application\Enums\MaintenanceStatus;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkClosureReason;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseMaintenanceWindowStore implements MaintenanceWindowStore
{
    private const RETURN_GRACE_SECONDS = 600;

    public function __construct(
        private ConnectionInterface $database,
        private BreakSessionStore $breaks,
        private OtherWorkSessionStore $otherWork,
    ) {}

    public function schedule(MaintenanceKind $kind, DateTimeImmutable $scheduledStartAt, string $reason): MaintenanceWindow
    {
        return $this->create($kind, MaintenanceStatus::Scheduled, $scheduledStartAt, null, $reason);
    }

    public function startNow(MaintenanceKind $kind, DateTimeImmutable $startedAt, string $reason): MaintenanceWindow
    {
        $window = $this->create($kind, MaintenanceStatus::Active, null, $startedAt, $reason);
        $this->snapshotActiveWork($window->id, $startedAt);

        return $window;
    }

    public function activate(int $maintenanceWindowId, DateTimeImmutable $startedAt): void
    {
        $this->database->transaction(function () use ($maintenanceWindowId, $startedAt): void {
            $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS)
                ->where('id', $maintenanceWindowId)
                ->where('status', MaintenanceStatus::Scheduled->value)
                ->update([
                    'status' => MaintenanceStatus::Active->value,
                    'started_at' => $startedAt,
                    'updated_at' => now(),
                ]);

            $this->snapshotActiveWork($maintenanceWindowId, $startedAt);
        });
    }

    public function complete(int $maintenanceWindowId, DateTimeImmutable $completedAt): void
    {
        $deadline = $completedAt->add(new DateInterval('PT'.self::RETURN_GRACE_SECONDS.'S'));

        $this->database->transaction(function () use ($maintenanceWindowId, $completedAt, $deadline): void {
            $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS)
                ->where('id', $maintenanceWindowId)
                ->where('status', MaintenanceStatus::Active->value)
                ->update([
                    'status' => MaintenanceStatus::Completed->value,
                    'completed_at' => $completedAt,
                    'updated_at' => now(),
                ]);

            $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS)
                ->where('maintenance_window_id', $maintenanceWindowId)
                ->whereNull('return_deadline_at')
                ->update([
                    'return_deadline_at' => $deadline,
                    'updated_at' => now(),
                ]);
        });
    }

    public function recordReturn(int $userId, DateTimeImmutable $returnedAt): bool
    {
        $updated = $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS)
            ->where('user_id', $userId)
            ->whereNull('returned_at')
            ->whereNotNull('return_deadline_at')
            ->where('return_deadline_at', '>=', $returnedAt)
            ->update([
                'returned_at' => $returnedAt,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    private function create(
        MaintenanceKind $kind,
        MaintenanceStatus $status,
        ?DateTimeImmutable $scheduledStartAt,
        ?DateTimeImmutable $startedAt,
        string $reason,
    ): MaintenanceWindow {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Maintenance reason cannot be empty.');
        }

        $now = now();
        $publicId = (string) Str::ulid();
        $id = $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_WINDOWS)->insertGetId([
            'public_id' => $publicId,
            'kind' => $kind->value,
            'status' => $status->value,
            'scheduled_start_at' => $scheduledStartAt,
            'started_at' => $startedAt,
            'completed_at' => null,
            'return_grace_seconds' => self::RETURN_GRACE_SECONDS,
            'reason' => $reason,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new MaintenanceWindow((int) $id, $publicId, $kind->value, $status->value);
    }

    private function snapshotActiveWork(int $maintenanceWindowId, DateTimeImmutable $startedAt): void
    {
        $sessions = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->whereNull('ended_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'team_id']);

        foreach ($sessions as $session) {
            $userId = $this->intValue($session->user_id ?? null);
            $now = now();

            $this->database->table(DatabaseTable::TIME_TRACKING_MAINTENANCE_AFFECTED_SESSIONS)->upsert([
                [
                    'public_id' => (string) Str::ulid(),
                    'maintenance_window_id' => $maintenanceWindowId,
                    'work_session_id' => $this->intValue($session->id ?? null),
                    'user_id' => $userId,
                    'team_id' => $this->intValue($session->team_id ?? null),
                    'interrupted_at' => $startedAt,
                    'return_deadline_at' => null,
                    'returned_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['maintenance_window_id', 'work_session_id'], ['interrupted_at', 'updated_at']);

            $this->breaks->closeActiveForUser($userId, BreakClosureReason::Forced, $startedAt, true);
            $this->otherWork->closeActiveForUser($userId, OtherWorkClosureReason::Forced, $startedAt, 'Maintenance interruption.');
        }
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
