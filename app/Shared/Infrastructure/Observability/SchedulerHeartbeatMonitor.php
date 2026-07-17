<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SchedulerHeartbeatMonitor
{
    public const DEFAULT_NAME = 'default';

    public function markRunning(string $name = self::DEFAULT_NAME): void
    {
        $now = CarbonImmutable::now();

        DB::table(DatabaseTable::SCHEDULER_HEARTBEATS)->updateOrInsert(
            ['name' => $name],
            [
                'status' => 'running',
                'last_started_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function markHealthy(int $runtimeMs, string $name = self::DEFAULT_NAME): void
    {
        $now = CarbonImmutable::now();

        DB::table(DatabaseTable::SCHEDULER_HEARTBEATS)->updateOrInsert(
            ['name' => $name],
            [
                'status' => 'healthy',
                'last_finished_at' => $now,
                'last_success_at' => $now,
                'last_runtime_ms' => max(0, $runtimeMs),
                'last_error' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function markFailed(Throwable $throwable, int $runtimeMs, string $name = self::DEFAULT_NAME): void
    {
        $now = CarbonImmutable::now();

        DB::table(DatabaseTable::SCHEDULER_HEARTBEATS)->updateOrInsert(
            ['name' => $name],
            [
                'status' => 'failed',
                'last_finished_at' => $now,
                'last_failed_at' => $now,
                'last_runtime_ms' => max(0, $runtimeMs),
                'last_error' => mb_substr($throwable->getMessage(), 0, 1_000),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function status(string $name = self::DEFAULT_NAME): array
    {
        $staleAfterSeconds = max(60, config()->integer('atlas.operations.scheduler_heartbeat_stale_seconds', 180));
        $row = DB::table(DatabaseTable::SCHEDULER_HEARTBEATS)->where('name', $name)->first();

        if ($row === null) {
            return $this->statusPayload(
                name: $name,
                status: 'missing',
                staleAfterSeconds: $staleAfterSeconds,
                description: 'No scheduler heartbeat has been recorded yet.',
            );
        }

        $values = get_object_vars($row);
        $lastSuccessAtValue = $values['last_success_at'] ?? null;
        $lastSuccessAt = $this->nullableString($lastSuccessAtValue);
        $status = $this->string($values['status'] ?? 'unknown');
        $isFresh = $this->isFresh($lastSuccessAtValue, $staleAfterSeconds);

        if ($status === 'healthy' && ! $isFresh) {
            $status = 'stale';
        }

        return $this->statusPayload(
            name: $name,
            status: $status,
            staleAfterSeconds: $staleAfterSeconds,
            description: $this->description($status),
            lastStartedAt: $this->nullableString($values['last_started_at'] ?? null),
            lastFinishedAt: $this->nullableString($values['last_finished_at'] ?? null),
            lastSuccessAt: $lastSuccessAt,
            lastFailedAt: $this->nullableString($values['last_failed_at'] ?? null),
            lastRuntimeMs: $this->nullableInt($values['last_runtime_ms'] ?? null),
            lastError: $this->nullableString($values['last_error'] ?? null),
            isFresh: $isFresh,
        );
    }

    private function isFresh(mixed $lastSuccessAt, int $staleAfterSeconds): bool
    {
        if ($lastSuccessAt === null) {
            return false;
        }

        $lastSuccess = $lastSuccessAt instanceof DateTimeInterface
            ? CarbonImmutable::instance($lastSuccessAt)->utc()
            : null;

        if ($lastSuccess === null && is_scalar($lastSuccessAt)) {
            $lastSuccess = CarbonImmutable::parse((string) $lastSuccessAt, 'UTC');
        }

        if ($lastSuccess === null) {
            return false;
        }

        return $lastSuccess->greaterThanOrEqualTo(CarbonImmutable::now('UTC')->subSeconds($staleAfterSeconds));
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function statusPayload(
        string $name,
        string $status,
        int $staleAfterSeconds,
        string $description,
        ?string $lastStartedAt = null,
        ?string $lastFinishedAt = null,
        ?string $lastSuccessAt = null,
        ?string $lastFailedAt = null,
        ?int $lastRuntimeMs = null,
        ?string $lastError = null,
        bool $isFresh = false,
    ): array {
        return [
            'name' => $name,
            'status' => $status,
            'label' => ucfirst($status),
            'description' => $description,
            'lastStartedAt' => $lastStartedAt,
            'lastFinishedAt' => $lastFinishedAt,
            'lastSuccessAt' => $lastSuccessAt,
            'lastFailedAt' => $lastFailedAt,
            'lastRuntimeMs' => $lastRuntimeMs,
            'lastError' => $lastError,
            'staleAfterSeconds' => $staleAfterSeconds,
            'isFresh' => $isFresh,
        ];
    }

    private function description(string $status): string
    {
        return match ($status) {
            'healthy' => 'Scheduler heartbeat is fresh.',
            'running' => 'Scheduler heartbeat is currently running or did not finish yet.',
            'failed' => 'The latest scheduler heartbeat failed.',
            'stale' => 'Scheduler heartbeat is older than the configured freshness threshold.',
            default => 'Scheduler heartbeat status is unknown.',
        };
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
