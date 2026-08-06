<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakSessionStore;
use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveBreakSession;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakClosureReason;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakReminderType;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseBreakSessionStore implements BreakSessionStore
{
    public function __construct(
        private ConnectionInterface $database,
        private BreakPolicyStore $policies,
        private WorkSessionStore $workSessions,
    ) {}

    public function startForActiveWorkSession(int $userId, DateTimeImmutable $startedAt): ActiveBreakSession
    {
        return $this->database->transaction(function () use ($userId, $startedAt): ActiveBreakSession {
            $workSession = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'team_id']);

            if (! is_object($workSession)) {
                throw new RuntimeException('Cannot start a TimeTracking break without an active work session.');
            }

            $existing = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'public_id', 'team_id', 'work_session_id', 'started_at']);

            if (is_object($existing)) {
                return $this->activeBreakFromRow($existing, $userId);
            }

            $now = now();
            $publicId = (string) Str::ulid();
            $workSessionId = $this->intValue($workSession->id ?? null);
            $teamId = $this->intValue($workSession->team_id ?? null);
            $id = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)->insertGetId([
                'public_id' => $publicId,
                'work_session_id' => $workSessionId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'started_at' => $startedAt,
                'ended_at' => null,
                'exact_seconds' => null,
                'closure_reason' => null,
                'requires_manager_review' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new ActiveBreakSession(
                id: (int) $id,
                publicId: $publicId,
                userId: $userId,
                teamId: $teamId,
                workSessionId: $workSessionId,
                startedAt: $startedAt,
            );
        });
    }

    public function closeActiveForUser(
        int $userId,
        BreakClosureReason $reason,
        DateTimeImmutable $endedAt,
        bool $requiresManagerReview,
    ): void {
        $this->database->transaction(function () use ($userId, $reason, $endedAt, $requiresManagerReview): void {
            $active = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'started_at']);

            if (! is_object($active)) {
                return;
            }

            $this->closeBreak(
                breakId: $this->intValue($active->id ?? null),
                startedAt: new DateTimeImmutable($this->stringValue($active->started_at ?? null)),
                endedAt: $endedAt,
                reason: $reason,
                requiresManagerReview: $requiresManagerReview,
            );
        });
    }

    public function closeExpired(DateTimeImmutable $now): int
    {
        $closed = 0;
        $activeBreaks = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get(['id', 'user_id', 'team_id', 'started_at']);

        foreach ($activeBreaks as $break) {
            $userId = $this->intValue($break->user_id ?? null);
            $teamId = $this->intValue($break->team_id ?? null);
            $startedAt = new DateTimeImmutable($this->stringValue($break->started_at ?? null));
            $policy = $this->policies->policyForUserTeam($userId, $teamId);
            $limitAt = $startedAt->add(new DateInterval('PT'.$policy->maximumSingleBreakSeconds.'S'));

            if ($now < $limitAt) {
                continue;
            }

            $this->database->transaction(function () use ($break, $startedAt, $limitAt, $userId): void {
                $this->closeBreak(
                    breakId: $this->intValue($break->id ?? null),
                    startedAt: $startedAt,
                    endedAt: $limitAt,
                    reason: BreakClosureReason::MaximumDuration,
                    requiresManagerReview: true,
                );
                $this->workSessions->closeActiveForUser($userId, WorkSessionClosureReason::BreakMaximumDuration, $limitAt);
            });

            $closed++;
        }

        return $closed;
    }

    public function recordDueReminders(DateTimeImmutable $now): int
    {
        $recorded = 0;
        $activeBreaks = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS.' as breaks')
            ->join(DatabaseTable::USERS.' as users', 'breaks.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS.' as teams', 'breaks.team_id', '=', 'teams.id')
            ->whereNull('breaks.ended_at')
            ->orderBy('breaks.started_at')
            ->get([
                'breaks.id',
                'breaks.public_id',
                'breaks.user_id',
                'breaks.team_id',
                'breaks.started_at',
                'users.public_id as user_public_id',
                'teams.public_id as team_public_id',
            ]);

        foreach ($activeBreaks as $break) {
            $userId = $this->intValue($break->user_id ?? null);
            $teamId = $this->intValue($break->team_id ?? null);
            $policy = $this->policies->policyForUserTeam($userId, $teamId);
            $startedAt = new DateTimeImmutable($this->stringValue($break->started_at ?? null));
            $dueAt = $startedAt->add(new DateInterval('PT'.($policy->maximumSingleBreakSeconds - $policy->warningBeforeMaximumSeconds).'S'));

            if ($now < $dueAt) {
                continue;
            }

            if ($this->recordReminder($break, BreakReminderType::BeforeMaximum, $dueAt, $now)) {
                $recorded++;
            }
        }

        return $recorded;
    }

    private function closeBreak(
        int $breakId,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        BreakClosureReason $reason,
        bool $requiresManagerReview,
    ): void {
        if ($breakId < 1) {
            return;
        }

        $seconds = max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());

        $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
            ->where('id', $breakId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => $endedAt,
                'exact_seconds' => $seconds,
                'closure_reason' => $reason->value,
                'requires_manager_review' => $requiresManagerReview,
                'updated_at' => now(),
            ]);
    }

    private function recordReminder(
        object $break,
        BreakReminderType $type,
        DateTimeImmutable $dueAt,
        DateTimeImmutable $recordedAt,
    ): bool {
        return $this->database->transaction(function () use ($break, $type, $dueAt, $recordedAt): bool {
            $breakId = $this->intValue($break->id ?? null);

            if ($breakId < 1) {
                return false;
            }

            $active = $this->database->table(DatabaseTable::TIME_TRACKING_BREAKS)
                ->where('id', $breakId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->exists();

            if (! $active || $this->reminderExists($breakId, $type)) {
                return false;
            }

            $this->database->table(DatabaseTable::TIME_TRACKING_BREAK_REMINDERS)->insert([
                'public_id' => (string) Str::ulid(),
                'break_id' => $breakId,
                'reminder_type' => $type->value,
                'due_at' => $dueAt,
                'recorded_at' => $recordedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    private function reminderExists(int $breakId, BreakReminderType $type): bool
    {
        return $this->database->table(DatabaseTable::TIME_TRACKING_BREAK_REMINDERS)
            ->where('break_id', $breakId)
            ->where('reminder_type', $type->value)
            ->exists();
    }

    private function activeBreakFromRow(object $row, int $userId): ActiveBreakSession
    {
        return new ActiveBreakSession(
            id: $this->intValue($row->id ?? null),
            publicId: $this->stringValue($row->public_id ?? null),
            userId: $userId,
            teamId: $this->intValue($row->team_id ?? null),
            workSessionId: $this->intValue($row->work_session_id ?? null),
            startedAt: new DateTimeImmutable($this->stringValue($row->started_at ?? null)),
        );
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
