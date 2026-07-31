<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\DataLifecycle\SharedDerivedDataLifecycleParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SharedDerivedDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_preview_reports_shared_cache_and_queued_work(): void
    {
        $subject = new DataLifecycleSubject('person', '01JSHAREDLIFECYCLE000001');
        $this->insertDerivedRows($subject->identifier);

        $preview = $this->app->make(SharedDerivedDataLifecycleParticipant::class)
            ->preview($subject, DataLifecycleOperation::Delete);

        $dataSets = collect($preview->impacts)->map->dataSet->all();

        self::assertContains('shared.cache', $dataSets);
        self::assertContains('shared.cache_locks', $dataSets);
        self::assertContains('shared.queued_jobs', $dataSets);
        self::assertSame([], $preview->blockers);
    }

    public function test_privacy_execution_removes_shared_cache_and_queued_work_idempotently(): void
    {
        $subject = new DataLifecycleSubject('person', '01JSHAREDLIFECYCLE000002');
        $this->insertDerivedRows($subject->identifier);

        $participant = $this->app->make(SharedDerivedDataLifecycleParticipant::class);
        $result = $participant->execute($subject, DataLifecycleOperation::Anonymize, (string) Str::uuid());
        $secondResult = $participant->execute($subject, DataLifecycleOperation::Anonymize, (string) Str::uuid());

        self::assertTrue($result->completed());
        self::assertTrue($secondResult->completed());
        self::assertSame([1, 1, 1], collect($result->steps)->map->affectedRecords->all());
        self::assertSame([0, 0, 0], collect($secondResult->steps)->map->affectedRecords->all());
        $this->assertDatabaseMissing(DatabaseTable::CACHE, ['key' => 'privacy:'.$subject->identifier]);
        $this->assertDatabaseMissing(DatabaseTable::CACHE_LOCKS, ['key' => 'privacy-lock:'.$subject->identifier]);
        $this->assertDatabaseMissing(DatabaseTable::JOBS, [
            'payload' => json_encode(['subject' => $subject->identifier], JSON_THROW_ON_ERROR),
        ]);
    }

    private function insertDerivedRows(string $subjectIdentifier): void
    {
        DB::table(DatabaseTable::CACHE)->insert([
            'key' => 'privacy:'.$subjectIdentifier,
            'value' => 'cached projection for '.$subjectIdentifier,
            'expiration' => now()->addHour()->unix(),
        ]);

        DB::table(DatabaseTable::CACHE_LOCKS)->insert([
            'key' => 'privacy-lock:'.$subjectIdentifier,
            'owner' => 'owner-'.$subjectIdentifier,
            'expiration' => now()->addMinute()->unix(),
        ]);

        DB::table(DatabaseTable::JOBS)->insert([
            'queue' => 'default',
            'payload' => json_encode(['subject' => $subjectIdentifier], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->unix(),
            'created_at' => now()->unix(),
        ]);
    }
}
