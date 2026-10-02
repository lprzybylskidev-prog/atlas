<?php

declare(strict_types=1);

namespace Tests\Integration\Chat;

use App\Modules\Core\Calendar\Application\Services\PersonalCalendar;
use App\Modules\Core\Calendar\Infrastructure\Persistence\TableNames\CalendarDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\Exceptions\MeetingOperationDenied;
use App\Modules\Optional\Chat\Application\MeetingManager;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
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

            try {
                $manager->remove((string) $participant->public_id, 'team', $meeting->publicId, (string) $additional->public_id);
                self::fail('A participant removed another Meeting participant.');
            } catch (MeetingOperationDenied $exception) {
                self::assertSame('Only the Meeting organizer may perform this operation.', $exception->getMessage());
            }

            $manager->remove((string) $organizer->public_id, 'team', $meeting->publicId, (string) $additional->public_id);
            $this->expectAccessDenied(fn () => $manager->show((string) $additional->public_id, 'team', $meeting->publicId));
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
