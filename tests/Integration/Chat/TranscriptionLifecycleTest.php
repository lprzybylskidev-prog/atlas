<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionProviderResponse;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionSource;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\MeetingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\TranscriptionManager;
use App\Modules\Optional\Chat\Application\TranscriptionProcess;
use App\Modules\Optional\Chat\Application\TranscriptionProcessor;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Chat\Infrastructure\Runtime\ContinueTranscriptionJob;
use App\Modules\Optional\Chat\Infrastructure\Transcription\DeterministicTranscriptionProvider;
use App\Modules\Optional\Chat\Infrastructure\Transcription\UnavailableTranscriptionProvider;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime\ExecuteManagedProcessJob;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TranscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->app->instance(ModuleGate::class, new class implements ModuleGate
        {
            public function inspect(ModuleAccessRequest $request): ModuleAccessDecision
            {
                return ModuleAccessDecision::allow();
            }

            public function allows(ModuleAccessRequest $request): bool
            {
                return true;
            }
        });
    }

    public function test_sync_provider_is_queued_idempotent_versioned_private_shareable_and_removed_with_recording(): void
    {
        [$organizer, $participant, $recipient, $other] = User::factory()->count(4)->create()->all();
        $team = Team::query()->create(['name' => 'transcription', 'display_name' => 'Transcription', 'is_active' => true]);
        $recording = $this->readyRecording($organizer, $participant);
        $this->provider(new DeterministicTranscriptionProvider(text: 'Original provider text.'));

        $manager = $this->app->make(TranscriptionManager::class);
        $queued = $manager->request((string) $participant->public_id, (string) $team->public_id, $recording['public_id']);
        self::assertSame('queued', $queued['status']);
        self::assertSame(1, DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->count());
        Queue::assertPushedOn('managed-processes', ExecuteManagedProcessJob::class);

        $run = DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->value('public_id');
        self::assertIsString($run);
        $this->app->make(TranscriptionProcessor::class)->process($run);

        $completed = $manager->request((string) $organizer->public_id, (string) $team->public_id, $recording['public_id']);
        self::assertSame('completed', $completed['status']);
        self::assertSame('Original provider text.', $completed['text']);
        self::assertCount(1, $this->requiredArray($completed, 'segments'));
        self::assertSame(1, DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->count(), 'A repeated request must not create another active or completed transcription run.');

        $transcriptionPublicId = $this->requiredString($completed, 'publicId');
        $projection = $this->app->make(ChatSearchProjectionStore::class);
        self::assertNotNull($projection->resolve('transcript-'.$transcriptionPublicId, $participant->id));
        self::assertNull($projection->resolve('transcript-'.$transcriptionPublicId, $recipient->id));
        $edited = $manager->edit((string) $participant->public_id, (string) $team->public_id, $transcriptionPublicId, 1, 'Participant correction.');
        $edited = $manager->edit((string) $organizer->public_id, (string) $team->public_id, $transcriptionPublicId, 2, 'Organizer correction.');
        self::assertSame(3, $edited['version']);
        $transcriptDocuments = $projection->documentsForSource('transcript', $transcriptionPublicId);
        self::assertCount(1, $transcriptDocuments);
        self::assertSame('Organizer correction.', $transcriptDocuments[0]->fields['body'] ?? null);
        $history = $this->requiredArray($edited, 'history');
        self::assertCount(3, $history);
        $initialVersion = $history[0] ?? null;
        self::assertIsArray($initialVersion);
        self::assertSame('Original provider text.', $initialVersion['text'] ?? null);

        $share = $manager->share((string) $participant->public_id, (string) $team->public_id, $transcriptionPublicId, (string) $recipient->public_id);
        $recipientView = $manager->view((string) $recipient->public_id, (string) $team->public_id, $transcriptionPublicId);
        self::assertSame('Organizer correction.', $recipientView['text']);
        self::assertSame([], $recipientView['history']);
        self::assertFalse($recipientView['canEdit']);
        self::assertFalse($recipientView['canShare']);
        $sharedProjection = $this->app->make(ChatSearchProjectionStore::class);
        $sharedSearchResult = $sharedProjection->resolve('transcript-'.$transcriptionPublicId, $recipient->id);
        self::assertNotNull($sharedSearchResult);
        self::assertSame('', $sharedSearchResult->title, 'A transcript-only share must not reveal the Meeting name.');
        self::assertNull($sharedSearchResult->conversationPublicId, 'A transcript-only share must not reveal or open Meeting chat.');
        try {
            $manager->share((string) $recipient->public_id, (string) $team->public_id, $transcriptionPublicId, (string) $other->public_id);
            self::fail('A transcript share recipient created an onward share.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting access requires an active invitation.', $exception->getMessage());
        }
        $manager->revoke((string) $participant->public_id, (string) $team->public_id, $transcriptionPublicId, $share['publicId']);
        $this->expectMeetingNotFound(fn () => $manager->view((string) $recipient->public_id, (string) $team->public_id, $transcriptionPublicId));
        self::assertNull($projection->resolve('transcript-'.$transcriptionPublicId, $recipient->id));

        DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->where('id', $recording['id'])->update(['ended_at' => now()->subDays(2)]);
        config(['chat.recording_retention_days' => 1]);
        self::assertSame(1, $this->app->make(MeetingRecordingRetention::class)->cleanup()['removed']);
        self::assertSame(0, DB::table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->count());
        self::assertSame(0, DB::table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)->count());
        self::assertSame(0, DB::table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)->count());
        self::assertNull($projection->resolve('transcript-'.$transcriptionPublicId, $participant->id));
        self::assertSame(
            ['transcript-'.$transcriptionPublicId],
            $projection->deletedDocumentIdsForSource('transcript', $transcriptionPublicId),
        );
    }

    public function test_async_provider_submit_poll_and_result_stay_in_one_waiting_managed_process(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $team = Team::query()->create(['name' => 'async-transcription', 'display_name' => 'Async transcription', 'is_active' => true]);
        $recording = $this->readyRecording($organizer, $participant);
        $this->provider(new DeterministicTranscriptionProvider(asynchronous: true, pendingPolls: 1, text: 'Async result.'));
        $manager = $this->app->make(TranscriptionManager::class);
        $queued = $manager->request((string) $organizer->public_id, (string) $team->public_id, $recording['public_id']);
        $run = DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->value('public_id');
        self::assertIsString($run);
        $processor = $this->app->make(TranscriptionProcessor::class);

        $processor->process($run);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, ['public_id' => $queued['publicId'], 'status' => 'submitted']);
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::RUNS, ['public_id' => $run, 'status' => 'waiting']);
        Queue::assertPushed(ContinueTranscriptionJob::class);

        $processor->process($run);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, ['public_id' => $queued['publicId'], 'status' => 'processing']);
        $processor->process($run);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, ['public_id' => $queued['publicId'], 'status' => 'completed']);
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::RUNS, ['public_id' => $run, 'status' => 'succeeded']);
    }

    public function test_provider_failures_back_off_then_reach_safe_failure_and_can_be_retried_manually(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $team = Team::query()->create(['name' => 'failed-transcription', 'display_name' => 'Failed transcription', 'is_active' => true]);
        $recording = $this->readyRecording($organizer, $participant);
        $this->provider(new AlwaysFailingTranscriptionProvider);
        config(['transcription.max_attempts' => 3, 'transcription.retry_backoff_seconds' => 1]);
        $manager = $this->app->make(TranscriptionManager::class);
        $queued = $manager->request((string) $organizer->public_id, (string) $team->public_id, $recording['public_id']);
        $run = DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->value('public_id');
        self::assertIsString($run);
        $processor = $this->app->make(TranscriptionProcessor::class);
        $processor->process($run);
        $processor->process($run);
        $processor->process($run);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, [
            'public_id' => $queued['publicId'], 'status' => 'failed', 'failure_code' => 'provider_failed', 'attempt_count' => 3,
        ]);
        $this->assertDatabaseHas(ManagedProcessesDatabaseTable::RUNS, ['public_id' => $run, 'status' => 'failed']);
        foreach (DB::table(ManagedProcessesDatabaseTable::LOG_EVENTS)->get(['message', 'safe_context']) as $event) {
            self::assertStringNotContainsString('secret provider failure', $this->scalarString($event->message ?? null));
            self::assertStringNotContainsString('secret provider failure', $this->scalarString($event->safe_context ?? null));
        }

        $managedRetry = $this->app->make(ManagedProcessRunner::class)->retry(
            $run,
            (string) $organizer->public_id,
            (string) $team->public_id,
            'Retry the failed provider request.',
        );
        $processor->process($managedRetry);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, [
            'public_id' => $queued['publicId'], 'status' => 'queued', 'attempt_count' => 1,
            'managed_process_run_public_id' => $managedRetry,
        ]);

        $processor->process($managedRetry);
        $processor->process($managedRetry);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, ['public_id' => $queued['publicId'], 'status' => 'failed']);

        $manager->request((string) $organizer->public_id, (string) $team->public_id, $recording['public_id']);
        self::assertSame(3, DB::table(ManagedProcessesDatabaseTable::RUNS)->where('process_key', TranscriptionProcess::KEY)->count());
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_TRANSCRIPTIONS, ['public_id' => $queued['publicId'], 'status' => 'queued', 'attempt_count' => 0]);
    }

    public function test_unavailable_provider_and_in_person_meeting_without_recording_have_no_transcription_path(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $team = Team::query()->create(['name' => 'no-provider', 'display_name' => 'No provider', 'is_active' => true]);
        $historicalRecording = $this->readyRecording($organizer, $participant);
        $this->provider(new UnavailableTranscriptionProvider);
        $manager = $this->app->make(TranscriptionManager::class);
        self::assertFalse($manager->available());
        self::assertNull($manager->forRecording((string) $organizer->public_id, (string) $team->public_id, $historicalRecording['public_id']));

        try {
            $manager->request((string) $organizer->public_id, (string) $team->public_id, (string) Str::ulid());
            self::fail('Unavailable provider accepted a request.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting transcription is unavailable.', $exception->getMessage());
        }

        $this->provider(new DeterministicTranscriptionProvider);
        $manager = $this->app->make(TranscriptionManager::class);
        self::assertSame('queued', $manager->request(
            (string) $organizer->public_id,
            (string) $team->public_id,
            $historicalRecording['public_id'],
        )['status']);
        $meeting = $this->app->make(MeetingManager::class)->create(
            (string) $organizer->public_id,
            (string) $team->public_id,
            new MeetingInput('In-person only', null, new DateTimeImmutable('+1 day'), new DateTimeImmutable('+1 day +1 hour'), MeetingMode::InPerson, 'Room 1', null, []),
        )['meeting'];
        self::assertSame('in_person', $meeting->mode->value);
        self::assertSame(0, DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->whereIn('occurrence_id', DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('meeting_id', $meeting->id)->pluck('id'))->count());
        self::assertSame(1, DB::table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->count());
    }

    /** @return array{id:int,public_id:string} */
    private function readyRecording(User $organizer, User $participant): array
    {
        $meeting = $this->app->make(MeetingManager::class)->create(
            (string) $organizer->public_id,
            'unused-team',
            new MeetingInput('Recorded Meeting', null, new DateTimeImmutable('+1 day'), new DateTimeImmutable('+1 day +1 hour'), MeetingMode::Online, null, null, [(string) $participant->public_id]),
        )['meeting'];
        $occurrenceId = DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('meeting_id', $meeting->id)->value('id');
        self::assertIsNumeric($occurrenceId);
        $file = $this->app->make(FileStorage::class)->storeGenerated('meeting.mp4', 'video/mp4', 'recording bytes', $organizer->id);
        $publicId = (string) Str::ulid();
        $id = (int) DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->insertGetId([
            'public_id' => $publicId,
            'occurrence_id' => (int) $occurrenceId,
            'initiated_by_user_id' => $organizer->id,
            'status' => 'ready',
            'file_public_id' => $file->publicId,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'public_id' => $publicId];
    }

    private function provider(TranscriptionProvider $provider): void
    {
        $this->app->instance(TranscriptionProvider::class, $provider);
        $this->app->forgetInstance(TranscriptionManager::class);
        $this->app->forgetInstance(TranscriptionProcessor::class);
    }

    private function expectMeetingNotFound(callable $operation): void
    {
        try {
            $operation();
            self::fail('Revoked transcript share retained access.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting not found.', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $values */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : throw new \RuntimeException('Expected string test value.');
    }

    /** @param array<string, mixed> $values
     * @return array<mixed>
     */
    private function requiredArray(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? $value : throw new \RuntimeException('Expected array test value.');
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

final class AlwaysFailingTranscriptionProvider implements TranscriptionProvider
{
    public function key(): string
    {
        return 'always-failing-test';
    }

    public function available(): bool
    {
        return true;
    }

    public function submit(TranscriptionSource $source, string $idempotencyKey): TranscriptionProviderResponse
    {
        throw new \RuntimeException('secret provider failure');
    }

    public function poll(string $externalJobId): TranscriptionProviderResponse
    {
        throw new \RuntimeException('secret provider failure');
    }
}
