<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use App\Shared\Application\Outbox\Contracts\OutboxEventRecorder;
use App\Shared\Application\Outbox\IntegrationEventMessage;
use App\Shared\Application\Outbox\OutboxEventStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OutboxRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_events_table_has_the_required_storage_contract(): void
    {
        self::assertTrue(Schema::hasColumns('outbox_events', [
            'id',
            'event_id',
            'event_type',
            'schema_version',
            'source_module',
            'payload',
            'occurred_at',
            'correlation_id',
            'causation_id',
            'status',
            'attempts',
            'next_attempt_at',
            'published_at',
            'failed_at',
            'failure_details',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_it_records_an_integration_event_as_pending_outbox_state(): void
    {
        $eventId = (string) Str::ulid();
        $correlationId = (string) Str::ulid();
        $causationId = (string) Str::ulid();
        $occurredAt = new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC'));

        $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
            eventId: $eventId,
            eventType: 'identity.user_registered',
            schemaVersion: 1,
            sourceModule: 'identity',
            payload: [
                'user_id' => '01J00000000000000000000000',
                'email' => 'person@example.test',
            ],
            occurredAt: $occurredAt,
            correlationId: $correlationId,
            causationId: $causationId,
        ));

        $record = DB::table('outbox_events')->where('event_id', $eventId)->first();

        self::assertNotNull($record);
        self::assertSame('identity.user_registered', $record->event_type);
        self::assertSame(1, $record->schema_version);
        self::assertSame('identity', $record->source_module);
        self::assertSame($correlationId, $record->correlation_id);
        self::assertSame($causationId, $record->causation_id);
        self::assertSame(OutboxEventStatus::Pending->value, $record->status);
        self::assertSame(0, $record->attempts);
        self::assertNull($record->next_attempt_at);
        self::assertNull($record->published_at);
        self::assertNull($record->failed_at);
        self::assertNull($record->failure_details);
        $payload = $record->payload;
        self::assertIsString($payload);

        self::assertEquals([
            'user_id' => '01J00000000000000000000000',
            'email' => 'person@example.test',
        ], json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_recorded_events_roll_back_with_the_owning_transaction(): void
    {
        $eventId = (string) Str::ulid();

        try {
            DB::transaction(function () use ($eventId): void {
                $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
                    eventId: $eventId,
                    eventType: 'identity.user_registered',
                    schemaVersion: 1,
                    sourceModule: 'identity',
                    payload: ['user_id' => '01J00000000000000000000000'],
                    occurredAt: new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC')),
                    correlationId: (string) Str::ulid(),
                ));

                throw new RuntimeException('Simulated application failure.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated application failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('outbox_events', [
            'event_id' => $eventId,
        ]);
    }

    public function test_recorded_events_commit_with_the_owning_transaction(): void
    {
        $eventId = (string) Str::ulid();

        DB::transaction(function () use ($eventId): void {
            $this->app->make(OutboxEventRecorder::class)->record(new IntegrationEventMessage(
                eventId: $eventId,
                eventType: 'identity.user_registered',
                schemaVersion: 1,
                sourceModule: 'identity',
                payload: ['user_id' => '01J00000000000000000000000'],
                occurredAt: new DateTimeImmutable('2026-07-15 11:00:00', new DateTimeZone('UTC')),
                correlationId: (string) Str::ulid(),
            ));
        });

        $this->assertDatabaseHas('outbox_events', [
            'event_id' => $eventId,
            'status' => OutboxEventStatus::Pending->value,
        ]);
    }
}
