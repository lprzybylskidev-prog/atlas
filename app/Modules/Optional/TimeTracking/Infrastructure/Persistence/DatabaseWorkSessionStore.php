<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\WorkSessionStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveWorkSession;
use App\Modules\Optional\TimeTracking\Application\Enums\WorkSessionClosureReason;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseWorkSessionStore implements WorkSessionStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function ensureActive(int $userId, int $teamId, string $laravelSessionId, DateTimeImmutable $startedAt): ActiveWorkSession
    {
        return $this->database->transaction(function () use ($userId, $teamId, $laravelSessionId, $startedAt): ActiveWorkSession {
            $active = $this->activeSessionForUser($userId);

            if (
                is_object($active)
                && $this->intValue($active->team_id ?? null) === $teamId
                && $this->stringValue($active->laravel_session_id ?? null) === $laravelSessionId
            ) {
                return new ActiveWorkSession(
                    id: $this->intValue($active->id ?? null),
                    publicId: $this->stringValue($active->public_id ?? null),
                );
            }

            if (is_object($active)) {
                $reason = $this->intValue($active->team_id ?? null) === $teamId
                    ? WorkSessionClosureReason::SessionSuperseded
                    : WorkSessionClosureReason::TeamSwitched;

                $this->closeOpenSession($this->intValue($active->id ?? null), $startedAt, $reason);
            }

            $now = now();

            $publicId = (string) Str::ulid();
            $id = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)->insertGetId([
                'public_id' => $publicId,
                'user_id' => $userId,
                'team_id' => $teamId,
                'laravel_session_id' => $laravelSessionId,
                'started_at' => $startedAt,
                'ended_at' => null,
                'exact_seconds' => null,
                'closure_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new ActiveWorkSession(id: (int) $id, publicId: $publicId);
        });
    }

    public function closeActiveForUser(int $userId, WorkSessionClosureReason $reason, DateTimeImmutable $endedAt): void
    {
        $this->database->transaction(function () use ($userId, $reason, $endedAt): void {
            $active = $this->activeSessionForUser($userId);

            if (! is_object($active)) {
                return;
            }

            $this->closeOpenSession($this->intValue($active->id ?? null), $endedAt, $reason);
        });
    }

    public function closeSession(int $sessionId, WorkSessionClosureReason $reason, DateTimeImmutable $endedAt): bool
    {
        if ($sessionId < 1) {
            return false;
        }

        return $this->database->transaction(function () use ($sessionId, $reason, $endedAt): bool {
            return $this->closeOpenSession($sessionId, $endedAt, $reason);
        });
    }

    public function recordModuleContext(int $workSessionId, string $moduleKey, string $contextKey, DateTimeImmutable $startedAt): void
    {
        if ($workSessionId < 1 || trim($moduleKey) === '' || trim($contextKey) === '') {
            return;
        }

        $this->database->transaction(function () use ($workSessionId, $moduleKey, $contextKey, $startedAt): void {
            $active = $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
                ->where('work_session_id', $workSessionId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first(['id', 'module_key', 'context_key', 'started_at']);

            if (
                is_object($active)
                && $this->stringValue($active->module_key ?? null) === $moduleKey
                && $this->stringValue($active->context_key ?? null) === $contextKey
            ) {
                return;
            }

            if (is_object($active)) {
                $this->closeModuleContextSegment($this->intValue($active->id ?? null), $startedAt);
            }

            $now = now();

            $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)->insert([
                'public_id' => (string) Str::ulid(),
                'work_session_id' => $workSessionId,
                'module_key' => $moduleKey,
                'context_key' => $contextKey,
                'started_at' => $startedAt,
                'ended_at' => null,
                'exact_seconds' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function activeSessionForUser(int $userId): ?object
    {
        return $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first(['id', 'public_id', 'team_id', 'laravel_session_id', 'started_at']);
    }

    private function closeOpenSession(int $sessionId, DateTimeImmutable $endedAt, WorkSessionClosureReason $reason): bool
    {
        if ($sessionId < 1) {
            return false;
        }

        $session = $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('id', $sessionId)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first(['id', 'started_at']);

        if (! is_object($session)) {
            return false;
        }

        $startedAt = new DateTimeImmutable($this->stringValue($session->started_at ?? null));
        $seconds = max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());

        $activeSegment = $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
            ->where('work_session_id', $sessionId)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first(['id']);

        if (is_object($activeSegment)) {
            $this->closeModuleContextSegment($this->intValue($activeSegment->id ?? null), $endedAt);
        }

        $this->database->table(DatabaseTable::TIME_TRACKING_WORK_SESSIONS)
            ->where('id', $sessionId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => $endedAt,
                'exact_seconds' => $seconds,
                'closure_reason' => $reason->value,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function closeModuleContextSegment(int $segmentId, DateTimeImmutable $endedAt): void
    {
        if ($segmentId < 1) {
            return;
        }

        $segment = $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
            ->where('id', $segmentId)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first(['id', 'started_at']);

        if (! is_object($segment)) {
            return;
        }

        $startedAt = new DateTimeImmutable($this->stringValue($segment->started_at ?? null));
        $seconds = max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());

        $this->database->table(DatabaseTable::TIME_TRACKING_MODULE_CONTEXT_SEGMENTS)
            ->where('id', $segmentId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => $endedAt,
                'exact_seconds' => $seconds,
                'updated_at' => now(),
            ]);
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
