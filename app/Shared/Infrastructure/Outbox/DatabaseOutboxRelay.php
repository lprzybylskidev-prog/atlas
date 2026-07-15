<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\Outbox\Contracts\OutboxEventPublisher;
use App\Shared\Application\Outbox\Contracts\OutboxRelay;
use App\Shared\Application\Outbox\OutboxEventStatus;
use App\Shared\Application\Outbox\OutboxRelayResult;
use App\Shared\Application\Outbox\OutboxStoredEvent;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;
use Throwable;
use UnexpectedValueException;

final readonly class DatabaseOutboxRelay implements OutboxRelay
{
    public function __construct(
        private ConnectionInterface $database,
        private OutboxEventPublisher $publisher,
        private int $maxAttempts = 3,
        private int $baseBackoffSeconds = 60,
        private int $maxBackoffSeconds = 3600,
    ) {}

    public function publishPending(int $limit = 100): OutboxRelayResult
    {
        if ($limit < 1) {
            throw new UnexpectedValueException('Outbox relay limit must be greater than zero.');
        }

        $events = $this->claimPendingEvents($limit);
        $published = 0;
        $scheduledForRetry = 0;
        $failed = 0;

        foreach ($events as $event) {
            try {
                $this->publisher->publish($event);
                $this->markPublished($event);
                $published++;
            } catch (Throwable $exception) {
                if ($this->markFailure($event, $exception)) {
                    $failed++;
                } else {
                    $scheduledForRetry++;
                }
            }
        }

        return new OutboxRelayResult(
            claimed: count($events),
            published: $published,
            scheduledForRetry: $scheduledForRetry,
            failed: $failed,
        );
    }

    /**
     * @return list<OutboxStoredEvent>
     */
    private function claimPendingEvents(int $limit): array
    {
        return $this->database->transaction(function () use ($limit): array {
            $now = $this->now();
            $records = $this->database
                ->table('outbox_events')
                ->where('status', OutboxEventStatus::Pending->value)
                ->where(function (Builder $query) use ($now): void {
                    $query
                        ->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', $now);
                })
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $events = [];
            $ids = [];

            foreach ($records as $record) {
                $events[] = $this->storedEventFromRecord($record);
                $ids[] = $this->intField($record, 'id');
            }

            if ($ids !== []) {
                $this->database
                    ->table('outbox_events')
                    ->whereIn('id', $ids)
                    ->update([
                        'status' => OutboxEventStatus::Publishing->value,
                        'updated_at' => $now,
                    ]);
            }

            return $events;
        });
    }

    private function markPublished(OutboxStoredEvent $event): void
    {
        $now = $this->now();

        $this->database
            ->table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEventStatus::Publishing->value)
            ->update([
                'status' => OutboxEventStatus::Published->value,
                'published_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function markFailure(OutboxStoredEvent $event, Throwable $exception): bool
    {
        $now = $this->now();
        $attempts = $event->attempts + 1;
        $failed = $attempts >= $this->maxAttempts;

        $this->database
            ->table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEventStatus::Publishing->value)
            ->update([
                'status' => $failed ? OutboxEventStatus::Failed->value : OutboxEventStatus::Pending->value,
                'attempts' => $attempts,
                'next_attempt_at' => $failed ? null : $this->nextAttemptAt($attempts),
                'failed_at' => $failed ? $now : null,
                'failure_details' => $this->failureDetails($exception),
                'updated_at' => $now,
            ]);

        return $failed;
    }

    private function nextAttemptAt(int $attempts): string
    {
        $seconds = min(
            $this->maxBackoffSeconds,
            $this->baseBackoffSeconds * (2 ** max(0, $attempts - 1)),
        );

        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('+%d seconds', $seconds))
            ->format('Y-m-d H:i:s.uP');
    }

    private function failureDetails(Throwable $exception): string
    {
        return mb_substr(sprintf('%s: %s', $exception::class, $exception->getMessage()), 0, 4000);
    }

    private function storedEventFromRecord(stdClass $record): OutboxStoredEvent
    {
        return new OutboxStoredEvent(
            id: $this->intField($record, 'id'),
            eventId: $this->stringField($record, 'event_id'),
            eventType: $this->stringField($record, 'event_type'),
            schemaVersion: $this->intField($record, 'schema_version'),
            sourceModule: $this->stringField($record, 'source_module'),
            payload: $this->payloadField($record),
            occurredAt: $this->dateTimeField($record, 'occurred_at'),
            correlationId: $this->stringField($record, 'correlation_id'),
            causationId: $this->nullableStringField($record, 'causation_id'),
            attempts: $this->intField($record, 'attempts'),
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP');
    }

    private function dateTimeField(stdClass $record, string $field): DateTimeImmutable
    {
        return new DateTimeImmutable($this->stringField($record, $field), new DateTimeZone('UTC'));
    }

    private function intField(stdClass $record, string $field): int
    {
        $value = $this->field($record, $field);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(sprintf('Outbox field [%s] must be an integer.', $field));
    }

    private function nullableStringField(stdClass $record, string $field): ?string
    {
        $value = $this->field($record, $field);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new UnexpectedValueException(sprintf('Outbox field [%s] must be a string or null.', $field));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadField(stdClass $record): array
    {
        $payload = json_decode($this->stringField($record, 'payload'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Outbox payload must decode to an array.');
        }

        $normalized = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Outbox payload must be a JSON object.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function stringField(stdClass $record, string $field): string
    {
        $value = $this->field($record, $field);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new UnexpectedValueException(sprintf('Outbox field [%s] must be a non-empty string.', $field));
    }

    private function field(stdClass $record, string $field): mixed
    {
        $values = get_object_vars($record);

        if (! array_key_exists($field, $values)) {
            throw new UnexpectedValueException(sprintf('Outbox record is missing field [%s].', $field));
        }

        return $values[$field];
    }
}
