<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use App\Shared\Application\Outbox\Contracts\OutboxEventPublisher;
use App\Shared\Application\Outbox\Contracts\OutboxEventRecorder;
use App\Shared\Application\Outbox\Contracts\OutboxMaintenance;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use App\Shared\Application\Outbox\OutboxEventStatus;
use App\Shared\Application\Outbox\OutboxStoredEvent;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxRelay;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_due_pending_events_and_marks_them_as_published(): void
    {
        $publisher = new RecordingOutboxPublisher;
        $relay = new DatabaseOutboxRelay(DB::connection(), $publisher);
        $eventId = (string) Str::ulid();

        $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
            eventId: $eventId,
            eventType: 'identity.user_registered',
            schemaVersion: 1,
            sourceModule: 'identity',
            payload: ['user_id' => '01J00000000000000000000000'],
            occurredAt: new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC')),
            correlationId: (string) Str::ulid(),
        ));

        $result = $relay->publishPending();

        self::assertSame(1, $result->claimed);
        self::assertSame(1, $result->published);
        self::assertCount(1, $publisher->publishedEvents);
        self::assertSame($eventId, $publisher->publishedEvents[0]->eventId);
        self::assertSame('identity.user_registered', $publisher->publishedEvents[0]->eventType);
        self::assertSame(['user_id' => '01J00000000000000000000000'], $publisher->publishedEvents[0]->payload);

        $this->assertDatabaseHas(DatabaseTable::OUTBOX_EVENTS, [
            'event_id' => $eventId,
            'status' => OutboxEventStatus::Published->value,
        ]);

        self::assertNotNull(DB::table(DatabaseTable::OUTBOX_EVENTS)->where('event_id', $eventId)->value('published_at'));
    }

    public function test_it_does_not_publish_events_that_are_not_due(): void
    {
        $publisher = new RecordingOutboxPublisher;
        $relay = new DatabaseOutboxRelay(DB::connection(), $publisher);
        $eventId = (string) Str::ulid();

        $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
            eventId: $eventId,
            eventType: 'identity.user_registered',
            schemaVersion: 1,
            sourceModule: 'identity',
            payload: ['user_id' => '01J00000000000000000000000'],
            occurredAt: new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC')),
            correlationId: (string) Str::ulid(),
        ));

        DB::table(DatabaseTable::OUTBOX_EVENTS)
            ->where('event_id', $eventId)
            ->update([
                'next_attempt_at' => '2999-01-01 00:00:00+00',
            ]);

        $result = $relay->publishPending();

        self::assertSame(0, $result->claimed);
        self::assertSame(0, $result->published);
        self::assertSame([], $publisher->publishedEvents);

        $this->assertDatabaseHas(DatabaseTable::OUTBOX_EVENTS, [
            'event_id' => $eventId,
            'status' => OutboxEventStatus::Pending->value,
        ]);
    }

    public function test_it_schedules_failed_publication_for_retry_with_bounded_backoff(): void
    {
        $publisher = new FailingOutboxPublisher;
        $relay = new DatabaseOutboxRelay(DB::connection(), $publisher, maxAttempts: 3, baseBackoffSeconds: 60, maxBackoffSeconds: 120);
        $eventId = $this->recordEvent();

        $result = $relay->publishPending();

        self::assertSame(1, $result->claimed);
        self::assertSame(0, $result->published);
        self::assertSame(1, $result->scheduledForRetry);
        self::assertSame(0, $result->failed);

        $record = DB::table(DatabaseTable::OUTBOX_EVENTS)->where('event_id', $eventId)->first();

        self::assertNotNull($record);
        self::assertSame(OutboxEventStatus::Pending->value, $record->status);
        self::assertSame(1, $record->attempts);
        self::assertNotNull($record->next_attempt_at);
        self::assertNull($record->failed_at);
        $failureDetails = $record->failure_details;
        self::assertIsString($failureDetails);
        self::assertStringContainsString('Simulated publisher failure.', $failureDetails);
    }

    public function test_it_moves_exhausted_events_to_failed_state(): void
    {
        $publisher = new FailingOutboxPublisher;
        $relay = new DatabaseOutboxRelay(DB::connection(), $publisher, maxAttempts: 1);
        $eventId = $this->recordEvent();

        $result = $relay->publishPending();

        self::assertSame(1, $result->claimed);
        self::assertSame(0, $result->published);
        self::assertSame(0, $result->scheduledForRetry);
        self::assertSame(1, $result->failed);

        $record = DB::table(DatabaseTable::OUTBOX_EVENTS)->where('event_id', $eventId)->first();

        self::assertNotNull($record);
        self::assertSame(OutboxEventStatus::Failed->value, $record->status);
        self::assertSame(1, $record->attempts);
        self::assertNull($record->next_attempt_at);
        self::assertNotNull($record->failed_at);
    }

    public function test_maintenance_can_replay_failed_events_without_changing_the_event_identity(): void
    {
        $publisher = new FailingOutboxPublisher;
        $relay = new DatabaseOutboxRelay(DB::connection(), $publisher, maxAttempts: 1);
        $eventId = $this->recordEvent();

        $relay->publishPending();

        $replayed = $this->app->make(OutboxMaintenance::class)->replayFailed($eventId);

        self::assertTrue($replayed);
        $this->assertDatabaseHas(DatabaseTable::OUTBOX_EVENTS, [
            'event_id' => $eventId,
            'status' => OutboxEventStatus::Pending->value,
            'attempts' => 0,
        ]);
    }

    public function test_maintenance_cleans_retained_published_and_failed_records(): void
    {
        $publishedEventId = $this->recordEvent();
        $failedEventId = $this->recordEvent();

        DB::table(DatabaseTable::OUTBOX_EVENTS)
            ->where('event_id', $publishedEventId)
            ->update([
                'status' => OutboxEventStatus::Published->value,
                'published_at' => '2000-01-01 00:00:00+00',
            ]);

        DB::table(DatabaseTable::OUTBOX_EVENTS)
            ->where('event_id', $failedEventId)
            ->update([
                'status' => OutboxEventStatus::Failed->value,
                'failed_at' => '2000-01-01 00:00:00+00',
            ]);

        $result = $this->app->make(OutboxMaintenance::class)->cleanup(
            publishedRetentionDays: 30,
            failedRetentionDays: 90,
        );

        self::assertSame(1, $result->deletedPublished);
        self::assertSame(1, $result->deletedFailed);
        $this->assertDatabaseMissing(DatabaseTable::OUTBOX_EVENTS, ['event_id' => $publishedEventId]);
        $this->assertDatabaseMissing(DatabaseTable::OUTBOX_EVENTS, ['event_id' => $failedEventId]);
    }

    public function test_maintenance_reports_outbox_lag_metrics(): void
    {
        $this->recordEvent();

        $metrics = $this->app->make(OutboxMaintenance::class)->lagMetrics();

        self::assertSame(1, $metrics->pending);
        self::assertSame(0, $metrics->publishing);
        self::assertSame(0, $metrics->failed);
        self::assertIsInt($metrics->oldestPendingAgeSeconds);
    }

    private function recordEvent(): string
    {
        $eventId = (string) Str::ulid();

        $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
            eventId: $eventId,
            eventType: 'identity.user_registered',
            schemaVersion: 1,
            sourceModule: 'identity',
            payload: ['user_id' => '01J00000000000000000000000'],
            occurredAt: new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC')),
            correlationId: (string) Str::ulid(),
        ));

        return $eventId;
    }
}

final class RecordingOutboxPublisher implements OutboxEventPublisher
{
    /** @var list<OutboxStoredEvent> */
    public array $publishedEvents = [];

    public function publish(OutboxStoredEvent $event): void
    {
        $this->publishedEvents[] = $event;
    }
}

final class FailingOutboxPublisher implements OutboxEventPublisher
{
    public function publish(OutboxStoredEvent $event): void
    {
        throw new RuntimeException('Simulated publisher failure.');
    }
}
