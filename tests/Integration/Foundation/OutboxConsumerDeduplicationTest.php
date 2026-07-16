<?php

declare(strict_types=1);

namespace Tests\Integration\Foundation;

use App\Shared\Application\Outbox\Contracts\OutboxConsumerDeduplicator;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutboxConsumerDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumed_events_table_has_the_required_storage_contract(): void
    {
        self::assertTrue(Schema::hasColumns(DatabaseTable::OUTBOX_CONSUMED_EVENTS, [
            'id',
            'event_id',
            'consumer',
            'consumed_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_it_records_the_first_consumer_delivery_and_rejects_duplicates(): void
    {
        $deduplicator = $this->app->make(OutboxConsumerDeduplicator::class);
        $eventId = (string) Str::ulid();

        self::assertTrue($deduplicator->recordIfFirst($eventId, 'notifications.email_projection'));
        self::assertFalse($deduplicator->recordIfFirst($eventId, 'notifications.email_projection'));
        self::assertTrue($deduplicator->recordIfFirst($eventId, 'audit.security_projection'));

        $this->assertDatabaseCount(DatabaseTable::OUTBOX_CONSUMED_EVENTS, 2);
    }
}
