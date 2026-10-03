<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Calendar\Application\Services\PersonalCalendar;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\Chat\Application\ChatModuleAccess;
use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingAssembler;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use App\Modules\Optional\Chat\Application\ConversationManager;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\RtcParticipantAccess;
use App\Modules\Optional\Chat\Application\DTOs\RtcRecordingStart;
use App\Modules\Optional\Chat\Application\DTOs\RtcSessionAdmission;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\MeetingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingAccessManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingFinalizer;
use App\Modules\Optional\Chat\Application\MeetingRecordingManager;
use App\Modules\Optional\Chat\Application\MeetingRecordingRetention;
use App\Modules\Optional\Chat\Application\MeetingReminderDispatcher;
use App\Modules\Optional\Chat\Application\MeetingRtcMaintenance;
use App\Modules\Optional\Chat\Application\MeetingRtcManager;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchClient;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchHit;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchQuery;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchResult;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MeetingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private RecordingMeetingNotifications $notifications;

    protected function setUp(): void
    {
        parent::setUp();
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
        $this->notifications = new RecordingMeetingNotifications;
        $this->app->instance(NotificationPublisher::class, $this->notifications);
        $this->app->forgetInstance(MeetingManager::class);
    }

    public function test_all_modes_keep_domain_calendar_chat_and_invitation_behavior_independent_from_rtc(): void
    {
        [$organizer, $participant, $additional] = User::factory()->count(3)->create()->all();
        $manager = $this->app->make(MeetingManager::class);

        foreach ([MeetingMode::Online, MeetingMode::InPerson, MeetingMode::Hybrid] as $index => $mode) {
            $result = $manager->create((string) $organizer->public_id, 'team', $this->input(
                $mode,
                [(string) $participant->public_id],
                $mode->requiresLocation() ? 'Room '.($index + 1) : null,
            ));
            $meeting = $result['meeting'];
            $this->assertDatabaseHas(ChatDatabaseTable::MEETINGS, ['public_id' => $meeting->publicId, 'mode' => $mode->value]);
            $this->assertDatabaseHas(ChatDatabaseTable::MEETING_OCCURRENCES, ['meeting_id' => $meeting->id, 'rtc_enabled' => $mode->hasRtc()]);
            $this->assertDatabaseHas(CalendarDatabaseTable::CONTRIBUTED_EVENTS, ['source_event_public_id' => $meeting->publicId, 'mode' => $mode->value]);
            $this->assertDatabaseHas(ChatDatabaseTable::CONVERSATIONS, ['public_id' => $meeting->conversationPublicId, 'type' => 'meeting']);

            $participantView = $manager->show((string) $participant->public_id, 'another-team', $meeting->publicId);
            self::assertSame('pending', $participantView['response']);
            self::assertSame($mode->hasRtc(), $participantView['canJoinOnline']);

            $manager->respond((string) $participant->public_id, 'team', $meeting->publicId, MeetingResponse::Declined);
            self::assertSame('declined', $manager->show((string) $participant->public_id, 'team', $meeting->publicId)['response']);
            $manager->respond((string) $participant->public_id, 'team', $meeting->publicId, MeetingResponse::Accepted);
            $manager->invite((string) $participant->public_id, 'team', $meeting->publicId, (string) $additional->public_id);
            self::assertSame('pending', $manager->show((string) $additional->public_id, 'team', $meeting->publicId)['response']);
            $search = new ChatSearch(
                new MeetingStaleSearchClient('conversation-'.$meeting->conversationPublicId),
                $this->app->make(ChatSearchProjectionStore::class),
                $this->app->make(ConversationManager::class),
                $this->app->make(ChatModuleAccess::class),
                $this->app->make(UserLookup::class),
            );
            self::assertCount(1, $search->query((string) $additional->public_id, 'team', 'Meeting')['items']);

            if ($mode->hasRtc()) {
                $occurrenceId = DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('meeting_id', $meeting->id)->value('id');
                self::assertIsNumeric($occurrenceId);
                DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update([
                    'rtc_status' => 'active',
                    'rtc_started_at' => now(),
                ]);
                DB::table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->insert([
                    'occurrence_id' => (int) $occurrenceId,
                    'user_id' => $additional->id,
                    'banned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                self::assertSame([], $search->query((string) $additional->public_id, 'team', 'Meeting')['items']);
                DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update([
                    'rtc_status' => 'ended',
                    'rtc_ended_at' => now(),
                ]);
                self::assertCount(1, $search->query((string) $additional->public_id, 'team', 'Meeting')['items']);
            }

            try {
                $manager->remove((string) $participant->public_id, 'team', $meeting->publicId, (string) $additional->public_id);
                self::fail('A participant removed another Meeting participant.');
            } catch (MeetingOperationDenied $exception) {
                self::assertSame('Only the Meeting organizer may perform this operation.', $exception->getMessage());
            }

            $manager->remove((string) $organizer->public_id, 'team', $meeting->publicId, (string) $additional->public_id);
            $this->expectAccessDenied(fn () => $manager->show((string) $additional->public_id, 'team', $meeting->publicId));
            self::assertSame([], $search->query((string) $additional->public_id, 'team', 'Meeting')['items']);
        }

        self::assertCount(6, $this->notifications->notifications);
    }

    public function test_recurring_series_uses_one_chat_supports_scoped_mutations_and_preserves_cancelled_history(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $manager = $this->app->make(MeetingManager::class);
        $input = new MeetingInput('Weekly plan', null, new DateTimeImmutable('2026-10-05 09:00 Europe/Warsaw'), new DateTimeImmutable('2026-10-05 10:00 Europe/Warsaw'),
            MeetingMode::Hybrid, 'Floor 2', new MeetingRecurrence('weekly', [1, 3], occurrenceCount: 8), [(string) $participant->public_id]);
        $meeting = $manager->create((string) $organizer->public_id, 'team', $input)['meeting'];

        self::assertSame($meeting->publicId, DB::table(ChatDatabaseTable::CONVERSATIONS)->where('public_id', $meeting->conversationPublicId)->value('meeting_owner_key'));
        $occurrenceInput = new MeetingInput('Special occurrence', null, new DateTimeImmutable('2026-10-07 14:00 Europe/Warsaw'), new DateTimeImmutable('2026-10-07 15:00 Europe/Warsaw'),
            MeetingMode::Online, null, $input->recurrence, [(string) $participant->public_id]);
        $futureInput = new MeetingInput('Future plan', null, new DateTimeImmutable('2026-10-12 11:00 Europe/Warsaw'), new DateTimeImmutable('2026-10-12 12:00 Europe/Warsaw'),
            MeetingMode::Hybrid, 'Floor 3', $input->recurrence, [(string) $participant->public_id]);
        $manager->update((string) $organizer->public_id, 'team', $meeting->publicId, $occurrenceInput, MeetingMutationScope::Occurrence, '2026-10-07');
        $manager->update((string) $organizer->public_id, 'team', $meeting->publicId, $futureInput, MeetingMutationScope::Future, '2026-10-12');
        self::assertSame(2, DB::table(ChatDatabaseTable::MEETING_MUTATIONS)->count());
        $calendar = $this->app->make(PersonalCalendar::class)->occurrences($participant->id, new DateTimeImmutable('2026-10-05 Europe/Warsaw'), new DateTimeImmutable('2026-10-15 Europe/Warsaw'));
        $byDate = [];
        foreach ($calendar as $event) {
            $byDate[$event->occurrenceDate] = $event;
        }
        self::assertSame('Special occurrence', $byDate['2026-10-07']->title);
        self::assertSame('online', $byDate['2026-10-07']->mode);
        self::assertSame('Future plan', $byDate['2026-10-12']->title);
        self::assertSame('11:00', substr($byDate['2026-10-14']->startsAt, 11, 5));

        $manager->cancel((string) $organizer->public_id, 'team', $meeting->publicId, MeetingMutationScope::Series, '2026-10-05');
        $this->assertDatabaseHas(ChatDatabaseTable::MEETINGS, ['public_id' => $meeting->publicId, 'status' => 'cancelled']);
        $this->assertDatabaseHas(CalendarDatabaseTable::CONTRIBUTED_EVENTS, ['source_event_public_id' => $meeting->publicId, 'cancelled' => true]);
        self::assertSame('cancelled', $manager->show((string) $participant->public_id, 'team', $meeting->publicId)['status']);
    }

    public function test_hybrid_rtc_attendance_and_empty_room_cleanup_do_not_change_meeting_domain_state(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $gateway = new RecordingMeetingRtcGateway;
        $this->app->instance(RtcGateway::class, $gateway);
        $this->app->forgetInstance(MeetingRtcManager::class);
        $this->app->forgetInstance(MeetingRtcMaintenance::class);
        $meeting = $this->app->make(MeetingManager::class)->create((string) $organizer->public_id, 'team', $this->input(MeetingMode::Hybrid, [(string) $participant->public_id], 'Room 1'))['meeting'];
        $date = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');
        $rtc = $this->app->make(MeetingRtcManager::class);
        $rtc->join((string) $participant->public_id, 'team', $meeting->publicId, $date, true, true);
        $rtc->leave((string) $participant->public_id, 'team', $meeting->publicId, $date);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_ATTENDANCE, ['user_id' => $participant->id]);
        DB::table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('meeting_id', $meeting->id)->update(['rtc_empty_since' => now()->subMinutes(16)]);
        self::assertSame(1, $this->app->make(MeetingRtcMaintenance::class)->endExpiredEmptySessions(new DateTimeImmutable('now')));
        $this->assertDatabaseHas(ChatDatabaseTable::MEETINGS, ['id' => $meeting->id, 'status' => 'scheduled']);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_OCCURRENCES, ['meeting_id' => $meeting->id, 'rtc_status' => 'ended']);
        self::assertCount(1, $gateway->endedRooms);
    }

    public function test_only_organizer_can_moderate_and_lock_an_online_meeting_occurrence(): void
    {
        [$organizer, $participant] = User::factory()->count(2)->create()->all();
        $this->app->instance(RtcGateway::class, new RecordingMeetingRtcGateway);
        $this->app->forgetInstance(MeetingRtcManager::class);
        $meeting = $this->app->make(MeetingManager::class)->create((string) $organizer->public_id, 'team', $this->input(MeetingMode::Online, [(string) $participant->public_id], null))['meeting'];
        $date = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');
        $rtc = $this->app->make(MeetingRtcManager::class);
        $rtc->join((string) $organizer->public_id, 'team', $meeting->publicId, $date, false, true);
        $rtc->join((string) $participant->public_id, 'team', $meeting->publicId, $date, true, true);
        try {
            $rtc->moderate((string) $participant->public_id, 'team', $meeting->publicId, $date, (string) $organizer->public_id, 'mute');
            self::fail('Participant moderated the organizer.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Only the Meeting organizer may perform this operation.', $exception->getMessage());
        }
        $rtc->moderate((string) $organizer->public_id, 'team', $meeting->publicId, $date, (string) $participant->public_id, 'disable_microphone');
        $rtc->lock((string) $organizer->public_id, 'team', $meeting->publicId, $date, true);
        try {
            $this->app->make(MeetingManager::class)->invite((string) $organizer->public_id, 'team', $meeting->publicId, (string) User::factory()->create()->public_id);
            self::fail('Locked Meeting accepted an invitation.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('The Meeting is locked.', $exception->getMessage());
        }
    }

    public function test_online_and_hybrid_recordings_are_organizer_controlled_segmented_and_structurally_recorded(): void
    {
        [$organizer, $participant, $recipient, $other] = User::factory()->count(4)->create()->all();
        $gateway = new RecordingMeetingRtcGateway;
        $this->app->instance(RtcGateway::class, $gateway);
        $this->app->forgetInstance(MeetingRtcManager::class);
        $this->app->forgetInstance(MeetingRecordingManager::class);
        $this->app->instance(MeetingRecordingAssembler::class, new ReadyMeetingRecordingAssembler);
        $this->app->forgetInstance(MeetingRecordingFinalizer::class);

        foreach ([MeetingMode::Online, MeetingMode::Hybrid] as $mode) {
            $meeting = $this->app->make(MeetingManager::class)->create(
                (string) $organizer->public_id,
                'team',
                $this->input($mode, [(string) $participant->public_id], $mode === MeetingMode::Hybrid ? 'Room 1' : null),
            )['meeting'];
            $date = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');
            $this->app->make(MeetingRtcManager::class)->join((string) $organizer->public_id, 'team', $meeting->publicId, $date, false, true);
            $recordings = $this->app->make(MeetingRecordingManager::class);

            try {
                $recordings->control((string) $participant->public_id, 'team', $meeting->publicId, $date, 'start');
                self::fail('A non-organizer started Meeting recording.');
            } catch (MeetingOperationDenied $exception) {
                self::assertSame('Only the Meeting organizer may perform this operation.', $exception->getMessage());
            }

            self::assertSame('recording', $recordings->control((string) $organizer->public_id, 'team', $meeting->publicId, $date, 'start')['status']);
            self::assertSame('paused', $recordings->control((string) $organizer->public_id, 'team', $meeting->publicId, $date, 'pause')['status']);
            self::assertSame('recording', $recordings->control((string) $organizer->public_id, 'team', $meeting->publicId, $date, 'resume')['status']);
            self::assertSame('processing', $recordings->control((string) $organizer->public_id, 'team', $meeting->publicId, $date, 'stop')['status']);
        }

        self::assertCount(4, $gateway->startedRecordings);
        self::assertCount(4, $gateway->stoppedEgressIds);
        self::assertSame(4, DB::table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)->count());
        self::assertSame(8, DB::table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->where('type', 'like', 'meeting.recording_%')->count());
        self::assertSame(['ready' => 2, 'failed' => 0, 'pending' => 0], $this->app->make(MeetingRecordingFinalizer::class)->finalizePending());
        self::assertSame(2, DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->where('status', 'ready')->whereNotNull('file_public_id')->count());

        $recordingPublicId = DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->orderBy('id')->value('public_id');
        self::assertIsString($recordingPublicId);
        $access = $this->app->make(MeetingRecordingAccessManager::class);
        self::assertNotNull($access->details((string) $participant->public_id, 'team', $recordingPublicId)['downloadUrl']);
        self::assertSame('video/mp4', $access->downloadable((string) $participant->public_id, 'team', $recordingPublicId)->mimeType);
        $share = $access->share((string) $organizer->public_id, 'team', $recordingPublicId, (string) $recipient->public_id);
        self::assertSame('ready', $access->details((string) $recipient->public_id, 'other-team', $recordingPublicId)['status']);
        try {
            $access->share((string) $recipient->public_id, 'team', $recordingPublicId, (string) $other->public_id);
            self::fail('A recording share recipient created an onward share.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting access requires an active invitation.', $exception->getMessage());
        }
        $access->revoke((string) $organizer->public_id, 'team', $recordingPublicId, $share['publicId']);
        try {
            $access->details((string) $recipient->public_id, 'team', $recordingPublicId);
            self::fail('A revoked recording recipient retained access.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting not found.', $exception->getMessage());
        }

        $access->share((string) $organizer->public_id, 'team', $recordingPublicId, (string) $recipient->public_id);
        DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->where('public_id', $recordingPublicId)->update(['ended_at' => now()->subDays(2)]);
        config(['chat.recording_retention_days' => 1]);
        self::assertSame(['removed' => 1, 'failed' => 0, 'disabled' => false], $this->app->make(MeetingRecordingRetention::class)->cleanup());
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_RECORDINGS, [
            'public_id' => $recordingPublicId,
            'status' => 'removed',
            'file_public_id' => null,
        ]);
        self::assertSame(0, DB::table(ChatDatabaseTable::MEETING_RECORDING_SHARES)->where('recording_id', DB::table(ChatDatabaseTable::MEETING_RECORDINGS)->where('public_id', $recordingPublicId)->value('id'))->count());
    }

    public function test_in_person_meeting_cannot_start_recording(): void
    {
        $organizer = User::factory()->create();
        $this->app->instance(RtcGateway::class, new RecordingMeetingRtcGateway);
        $this->app->forgetInstance(MeetingRecordingManager::class);
        $meeting = $this->app->make(MeetingManager::class)->create((string) $organizer->public_id, 'team', $this->input(MeetingMode::InPerson, [], 'Room 2'))['meeting'];
        $date = $meeting->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d');

        $this->expectException(MeetingOperationDenied::class);
        $this->app->make(MeetingRecordingManager::class)->control((string) $organizer->public_id, 'team', $meeting->publicId, $date, 'start');
    }

    public function test_accepted_meeting_participant_receives_one_preference_controlled_reminder(): void
    {
        $organizer = User::factory()->create();
        $meeting = $this->app->make(MeetingManager::class)->create(
            (string) $organizer->public_id,
            'team',
            $this->input(MeetingMode::InPerson, [], 'Room 2'),
        )['meeting'];
        $dispatcher = $this->app->make(MeetingReminderDispatcher::class);
        $now = new DateTimeImmutable('2026-10-10 06:45 UTC');

        self::assertSame(1, $dispatcher->dispatch($now));
        self::assertSame(0, $dispatcher->dispatch($now));
        self::assertCount(1, $this->notifications->notifications);
        self::assertSame('chat.meeting.reminder', $this->notifications->notifications[0]->type);
        self::assertTrue($this->notifications->notifications[0]->emailRequested);
        $this->assertDatabaseHas(ChatDatabaseTable::MEETING_REMINDER_DELIVERIES, [
            'meeting_id' => $meeting->id,
            'user_id' => $organizer->id,
            'occurrence_date' => '2026-10-10',
            'minutes_before' => 15,
        ]);
    }

    /** @param list<string> $invitees */
    private function input(MeetingMode $mode, array $invitees, ?string $location): MeetingInput
    {
        return new MeetingInput('Mode '.$mode->value, 'Details', new DateTimeImmutable('2026-10-10 09:00 Europe/Warsaw'),
            new DateTimeImmutable('2026-10-10 10:00 Europe/Warsaw'), $mode, $location, null, $invitees);
    }

    private function expectAccessDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('Removed participant retained Meeting access.');
        } catch (MeetingOperationDenied $exception) {
            self::assertSame('Meeting access requires an active invitation.', $exception->getMessage());
        }
    }
}

final readonly class MeetingStaleSearchClient implements SearchClient
{
    public function __construct(private string $documentId) {}

    public function search(SearchQuery $query): SearchResult
    {
        return new SearchResult($query->indexKey, [new SearchHit($this->documentId, 'chat', [])], 1);
    }
}

final class ReadyMeetingRecordingAssembler implements MeetingRecordingAssembler
{
    public function segmentsReady(array $segmentPaths): bool
    {
        return $segmentPaths !== [];
    }

    public function assemble(array $segmentPaths, string $recordingPublicId): string
    {
        $path = sys_get_temp_dir().'/atlas-recording-'.$recordingPublicId.'.mp4';
        file_put_contents($path, 'finalized recording');

        return $path;
    }

    public function cleanup(array $segmentPaths, string $finalPath): void
    {
        @unlink($finalPath);
    }
}

final class RecordingMeetingNotifications implements NotificationPublisher
{
    /** @var list<CreateNotification> */
    public array $notifications = [];

    public function publish(CreateNotification $notification): string
    {
        $this->notifications[] = $notification;

        return 'notification-'.count($this->notifications);
    }
}

final class RecordingMeetingRtcGateway implements RtcGateway
{
    /** @var list<string> */
    public array $endedRooms = [];

    /** @var list<string> */
    public array $startedRecordings = [];

    /** @var list<string> */
    public array $stoppedEgressIds = [];

    public function prepareRoom(RtcSessionAdmission $admission): void {}

    public function issueParticipantAccess(RtcSessionAdmission $admission): RtcParticipantAccess
    {
        return new RtcParticipantAccess('ws://rtc.test', $admission->roomName, 'user-'.$admission->userPublicId, 'token', new DateTimeImmutable('+5 minutes'));
    }

    public function removeParticipant(string $roomName, string $participantIdentity): void {}

    public function endRoom(string $roomName): void
    {
        $this->endedRooms[] = $roomName;
    }

    public function startRoomCompositeRecording(string $roomName, string $recordingPublicId, int $segment): RtcRecordingStart
    {
        $egressId = 'egress-'.count($this->startedRecordings).'-'.$segment;
        $this->startedRecordings[] = $roomName;

        return new RtcRecordingStart($egressId, 'recordings/'.$recordingPublicId.'/'.$segment.'.mp4');
    }

    public function stopRoomCompositeRecording(string $egressId): void
    {
        $this->stoppedEgressIds[] = $egressId;
    }
}
