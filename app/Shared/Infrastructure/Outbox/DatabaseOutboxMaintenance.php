<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Outbox\Contracts\OutboxMaintenance;
use App\Shared\Application\Outbox\OutboxCleanupResult;
use App\Shared\Application\Outbox\OutboxEventStatus;
use App\Shared\Application\Outbox\OutboxLagMetrics;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseOutboxMaintenance implements OutboxMaintenance
{
    public function __construct(private ConnectionInterface $database) {}

    public function cleanup(int $publishedRetentionDays = 30, int $failedRetentionDays = 90): OutboxCleanupResult
    {
        $publishedCutoff = $this->daysAgo($publishedRetentionDays);
        $failedCutoff = $this->daysAgo($failedRetentionDays);

        $deletedPublished = $this->database
            ->table('outbox_events')
            ->where('status', OutboxEventStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<', $publishedCutoff)
            ->delete();

        $deletedFailed = $this->database
            ->table('outbox_events')
            ->where('status', OutboxEventStatus::Failed->value)
            ->whereNotNull('failed_at')
            ->where('failed_at', '<', $failedCutoff)
            ->delete();

        return new OutboxCleanupResult(
            deletedPublished: $deletedPublished,
            deletedFailed: $deletedFailed,
        );
    }

    public function replayFailed(string $eventId): bool
    {
        $updated = $this->database
            ->table('outbox_events')
            ->where('event_id', $eventId)
            ->where('status', OutboxEventStatus::Failed->value)
            ->update([
                'status' => OutboxEventStatus::Pending->value,
                'attempts' => 0,
                'next_attempt_at' => null,
                'failed_at' => null,
                'failure_details' => null,
                'updated_at' => $this->now(),
            ]);

        return $updated === 1;
    }

    public function lagMetrics(): OutboxLagMetrics
    {
        $oldestPending = $this->database
            ->table('outbox_events')
            ->where('status', OutboxEventStatus::Pending->value)
            ->min('occurred_at');

        return new OutboxLagMetrics(
            pending: $this->countStatus(OutboxEventStatus::Pending),
            publishing: $this->countStatus(OutboxEventStatus::Publishing),
            failed: $this->countStatus(OutboxEventStatus::Failed),
            oldestPendingAgeSeconds: is_string($oldestPending) ? $this->ageSeconds($oldestPending) : null,
        );
    }

    private function countStatus(OutboxEventStatus $status): int
    {
        return $this->database
            ->table('outbox_events')
            ->where('status', $status->value)
            ->count();
    }

    private function ageSeconds(string $timestamp): int
    {
        $occurredAt = new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));

        return max(0, (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp() - $occurredAt->getTimestamp());
    }

    private function daysAgo(int $days): string
    {
        if ($days < 0) {
            $days = 0;
        }

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('-%d days', $days))
            ->format('Y-m-d H:i:s.uP');
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');
    }
}
