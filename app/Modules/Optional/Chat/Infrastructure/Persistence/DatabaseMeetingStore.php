<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInvitationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRtcSession;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMode;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingMutationScope;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecurrence;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRole;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;
use Symfony\Component\Uid\Ulid;

final readonly class DatabaseMeetingStore implements MeetingStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function lock(string $key): void
    {
        $this->database->select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
    }

    public function create(string $publicId, int $organizerUserId, string $conversationPublicId, MeetingInput $input): MeetingRecord
    {
        $conversationId = $this->database->table(ChatDatabaseTable::CONVERSATIONS)->where('public_id', $conversationPublicId)->value('id');
        if (! is_numeric($conversationId)) {
            throw new \InvalidArgumentException('Meeting conversation does not exist.');
        }
        $id = $this->database->table(ChatDatabaseTable::MEETINGS)->insertGetId([
            'public_id' => $publicId,
            'series_public_id' => $publicId,
            'organizer_user_id' => $organizerUserId,
            'conversation_id' => (int) $conversationId,
            ...$this->values($input),
            'status' => MeetingStatus::Scheduled->value,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->insert([
            'public_id' => (string) new Ulid,
            'meeting_id' => $id,
            'occurrence_date' => $input->startsAt->setTimezone(new \DateTimeZone('Europe/Warsaw'))->format('Y-m-d'),
            'rtc_enabled' => $input->mode->hasRtc(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->find($publicId) ?? throw new \RuntimeException('Created Meeting could not be loaded.');
    }

    public function find(string $publicId, bool $forUpdate = false): ?MeetingRecord
    {
        $query = $this->database->table(ChatDatabaseTable::MEETINGS.' as meetings')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'meetings.conversation_id')
            ->where('meetings.public_id', $publicId)
            ->select('meetings.*', 'conversations.public_id as conversation_public_id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->mapMeeting($row) : null;
    }

    public function forUser(int $userId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETINGS.' as meetings')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'conversations.id', '=', 'meetings.conversation_id')
            ->join(ChatDatabaseTable::MEETING_INVITATIONS.' as invitations', 'invitations.meeting_id', '=', 'meetings.id')
            ->where('invitations.user_id', $userId)->whereNull('invitations.removed_at')
            ->orderBy('meetings.starts_at')->select('meetings.*', 'conversations.public_id as conversation_public_id')
            ->get()->map(fn (stdClass $row): MeetingRecord => $this->mapMeeting($row))->all());
    }

    public function invitations(int $meetingId, bool $activeOnly = true): array
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)->where('meeting_id', $meetingId)->orderBy('id');
        if ($activeOnly) {
            $query->whereNull('removed_at');
        }

        return array_values($query->get()->map(fn (stdClass $row): MeetingInvitationRecord => $this->mapInvitation($row))->all());
    }

    public function invitation(int $meetingId, int $userId, bool $forUpdate = false): ?MeetingInvitationRecord
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)
            ->where('meeting_id', $meetingId)->where('user_id', $userId)->whereNull('removed_at')->latest('id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->mapInvitation($row) : null;
    }

    public function invite(int $meetingId, int $userId, int $invitedByUserId, MeetingRole $role, MeetingResponse $response): MeetingInvitationRecord
    {
        $id = $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)->insertGetId([
            'public_id' => (string) new Ulid, 'meeting_id' => $meetingId, 'user_id' => $userId,
            'invited_by_user_id' => $invitedByUserId, 'role' => $role->value, 'response' => $response->value,
            'responded_at' => $response === MeetingResponse::Pending ? null : now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)->find($id);

        return $this->mapInvitation($row instanceof stdClass ? $row : throw new \RuntimeException('Invitation missing.'));
    }

    public function respond(int $invitationId, MeetingResponse $response): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)->where('id', $invitationId)->whereNull('removed_at')->update([
            'response' => $response->value, 'responded_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function remove(int $invitationId, int $removedByUserId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_INVITATIONS)->where('id', $invitationId)->whereNull('removed_at')->update([
            'removed_by_user_id' => $removedByUserId, 'removed_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function update(MeetingRecord $meeting, MeetingInput $input, MeetingMutationScope $scope, string $occurrenceDate): void
    {
        if ($scope === MeetingMutationScope::Series || $meeting->recurrence === null) {
            $this->database->table(ChatDatabaseTable::MEETINGS)->where('id', $meeting->id)->where('version', $meeting->version)->update([
                ...$this->values($input), 'version' => $meeting->version + 1, 'updated_at' => now(),
            ]);

            return;
        }
        $this->database->table(ChatDatabaseTable::MEETING_MUTATIONS)->updateOrInsert(
            ['meeting_id' => $meeting->id, 'effective_date' => $occurrenceDate, 'scope' => $scope->value],
            ['public_id' => (string) new Ulid, 'cancelled' => false, 'payload' => json_encode($this->payload($input), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function cancel(MeetingRecord $meeting, MeetingMutationScope $scope, string $occurrenceDate): void
    {
        if ($scope === MeetingMutationScope::Series || $meeting->recurrence === null) {
            $this->database->table(ChatDatabaseTable::MEETINGS)->where('id', $meeting->id)->update([
                'status' => MeetingStatus::Cancelled->value, 'cancelled_at' => now(), 'version' => $meeting->version + 1, 'updated_at' => now(),
            ]);

            return;
        }
        $this->database->table(ChatDatabaseTable::MEETING_MUTATIONS)->updateOrInsert(
            ['meeting_id' => $meeting->id, 'effective_date' => $occurrenceDate, 'scope' => $scope->value],
            ['public_id' => (string) new Ulid, 'cancelled' => true, 'payload' => null, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function mutations(int $meetingId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_MUTATIONS)->where('meeting_id', $meetingId)->orderBy('effective_date')->orderBy('scope')->get()->map(function (stdClass $row): array {
            return [
                'effective_date' => substr($this->requiredString($row->effective_date), 0, 10),
                'scope' => $this->requiredString($row->scope),
                'cancelled' => $row->cancelled === true || $row->cancelled === 1,
                'payload' => $this->payloadArray($row->payload),
            ];
        })->all());
    }

    public function rtcSession(MeetingRecord $meeting, string $occurrenceDate, bool $forUpdate = false): ?MeetingRtcSession
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)
            ->where('meeting_id', $meeting->id)->where('occurrence_date', $occurrenceDate);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if (! $row instanceof stdClass || ! ($row->rtc_enabled === true || $row->rtc_enabled === 1)) {
            return null;
        }

        return $this->mapRtcSession($row);
    }

    public function startRtcSession(int $occurrenceId, string $roomName): MeetingRtcSession
    {
        $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update([
            'rtc_room_name' => $roomName, 'rtc_status' => 'active', 'rtc_started_at' => now(), 'rtc_ended_at' => null,
            'rtc_empty_since' => null, 'updated_at' => now(),
        ]);

        return $this->sessionByOccurrence($occurrenceId);
    }

    public function joinRtcParticipant(int $occurrenceId, int $userId, bool $cameraEnabled, bool $microphoneEnabled): void
    {
        $existing = $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)
            ->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->lockForUpdate()->first();
        if ($existing instanceof stdClass && $existing->banned_at !== null) {
            throw new \LogicException('The participant is banned from this Meeting occurrence.');
        }
        $allowed = ! ($existing instanceof stdClass) || $existing->microphone_allowed === true || $existing->microphone_allowed === 1;
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->updateOrInsert(
            ['occurrence_id' => $occurrenceId, 'user_id' => $userId],
            ['camera_enabled' => $cameraEnabled, 'microphone_enabled' => $microphoneEnabled && $allowed,
                'microphone_allowed' => $allowed, 'screen_sharing' => false, 'joined_at' => now(), 'left_at' => null, 'updated_at' => now(), 'created_at' => now()],
        );
        $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update(['rtc_empty_since' => null, 'updated_at' => now()]);
        $this->database->table(ChatDatabaseTable::MEETING_ATTENDANCE)->insert(['occurrence_id' => $occurrenceId, 'user_id' => $userId, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function leaveRtcParticipant(int $occurrenceId, int $userId): void
    {
        $now = now();
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->whereNull('left_at')->update(['left_at' => $now, 'screen_sharing' => false, 'updated_at' => $now]);
        $attendance = $this->database->table(ChatDatabaseTable::MEETING_ATTENDANCE)->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->whereNull('left_at')->latest('id')->first();
        if ($attendance instanceof stdClass) {
            $joined = new DateTimeImmutable($this->requiredString($attendance->joined_at));
            $this->database->table(ChatDatabaseTable::MEETING_ATTENDANCE)->where('id', $attendance->id)->update(['left_at' => $now, 'duration_seconds' => max(0, $now->diffInSeconds($joined)), 'updated_at' => $now]);
        }
        $joinedCount = $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->whereNull('left_at')->count();
        if ($joinedCount === 0) {
            $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->whereNull('rtc_empty_since')->update(['rtc_empty_since' => $now, 'updated_at' => $now]);
        }
    }

    public function setRtcParticipantMedia(int $occurrenceId, int $userId, bool $cameraEnabled, bool $microphoneEnabled): void
    {
        $participant = $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->whereNull('left_at')->lockForUpdate()->first();
        if (! $participant instanceof stdClass) {
            throw new \LogicException('The participant is not connected.');
        }
        $allowed = $participant->microphone_allowed === true || $participant->microphone_allowed === 1;
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('id', $participant->id)->update(['camera_enabled' => $cameraEnabled, 'microphone_enabled' => $microphoneEnabled && $allowed, 'updated_at' => now()]);
    }

    public function setRtcParticipantScreenShare(int $occurrenceId, int $userId, bool $active): void
    {
        if ($active && $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->where('screen_sharing', true)->where('user_id', '!=', $userId)->exists()) {
            throw new \LogicException('A screen share is already active.');
        }
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->whereNull('left_at')->update(['screen_sharing' => $active, 'updated_at' => now()]);
    }

    public function setRtcParticipantMicrophoneAllowed(int $occurrenceId, int $userId, bool $allowed): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $occurrenceId)->where('user_id', $userId)->whereNull('left_at')->update(['microphone_allowed' => $allowed, 'microphone_enabled' => $allowed ? $this->database->raw('microphone_enabled') : false, 'updated_at' => now()]);
    }

    public function banRtcParticipant(int $occurrenceId, int $userId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->updateOrInsert(['occurrence_id' => $occurrenceId, 'user_id' => $userId], ['banned_at' => now(), 'left_at' => now(), 'screen_sharing' => false, 'updated_at' => now(), 'created_at' => now()]);
    }

    public function setRtcLocked(int $occurrenceId, bool $locked): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update(['rtc_locked' => $locked, 'updated_at' => now()]);
    }

    public function endRtcSession(int $occurrenceId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->update(['rtc_status' => 'ended', 'rtc_ended_at' => now(), 'rtc_empty_since' => null, 'updated_at' => now()]);
    }

    public function attendance(MeetingRecord $meeting): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_ATTENDANCE.' as attendance')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.id', '=', 'attendance.occurrence_id')
            ->where('occurrences.meeting_id', $meeting->id)->orderBy('attendance.joined_at')
            ->get()->map(fn (stdClass $row): array => ['userId' => $this->requiredInt($row->user_id), 'joinedAt' => $this->requiredString($row->joined_at), 'leftAt' => is_string($row->left_at) ? $row->left_at : null, 'durationSeconds' => is_numeric($row->duration_seconds) ? (int) $row->duration_seconds : null, 'occurrenceDate' => $this->requiredString($row->occurrence_date)])->all());
    }

    public function endExpiredEmptyRtcSessions(DateTimeImmutable $now): array
    {
        $threshold = $now->modify('-15 minutes')->format(DATE_ATOM);
        $rows = $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)
            ->where('rtc_status', 'active')->whereNotNull('rtc_empty_since')->where('rtc_empty_since', '<=', $threshold)
            ->lockForUpdate()->get();
        $rooms = [];
        foreach ($rows as $row) {
            if (! is_string($row->rtc_room_name)) {
                continue;
            }
            $activeParticipants = $this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $row->id)->whereNull('left_at')->count();
            if ($activeParticipants !== 0) {
                continue;
            }
            $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $row->id)->where('rtc_status', 'active')->update(['rtc_status' => 'ended', 'rtc_ended_at' => $now->format(DATE_ATOM), 'rtc_empty_since' => null, 'updated_at' => $now->format(DATE_ATOM)]);
            $rooms[] = $row->rtc_room_name;
        }

        return $rooms;
    }

    private function sessionByOccurrence(int $occurrenceId): MeetingRtcSession
    {
        $row = $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('id', $occurrenceId)->first();
        if (! $row instanceof stdClass) {
            throw new \RuntimeException('Meeting occurrence is missing.');
        }

        return $this->mapRtcSession($row);
    }

    private function mapRtcSession(stdClass $row): MeetingRtcSession
    {
        $participants = array_values($this->database->table(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS)->where('occurrence_id', $this->requiredInt($row->id))->orderBy('id')->get()->map(fn (stdClass $item): array => ['userId' => $this->requiredInt($item->user_id), 'microphoneEnabled' => $item->microphone_enabled === true || $item->microphone_enabled === 1, 'microphoneAllowed' => $item->microphone_allowed === true || $item->microphone_allowed === 1, 'cameraEnabled' => $item->camera_enabled === true || $item->camera_enabled === 1, 'screenSharing' => $item->screen_sharing === true || $item->screen_sharing === 1, 'joinedAt' => is_string($item->joined_at) ? $item->joined_at : null, 'leftAt' => is_string($item->left_at) ? $item->left_at : null, 'bannedAt' => is_string($item->banned_at) ? $item->banned_at : null])->all());

        return new MeetingRtcSession($this->requiredInt($row->id), is_string($row->rtc_room_name) ? $row->rtc_room_name : '', $row->rtc_locked === true || $row->rtc_locked === 1, is_string($row->rtc_started_at) ? $row->rtc_started_at : null, is_string($row->rtc_ended_at) ? $row->rtc_ended_at : null, is_string($row->rtc_empty_since) ? $row->rtc_empty_since : null, $participants);
    }

    /** @return array<string,mixed> */
    private function values(MeetingInput $input): array
    {
        return ['title' => $input->title, 'description' => $input->description, 'starts_at' => $input->startsAt->format(DATE_ATOM),
            'ends_at' => $input->endsAt->format(DATE_ATOM), 'mode' => $input->mode->value, 'location' => $input->location,
            'recurrence_frequency' => $input->recurrence?->frequency, 'recurrence_weekdays' => $input->recurrence === null ? null : json_encode($input->recurrence->weekdays, JSON_THROW_ON_ERROR),
            'recurrence_ends_on' => $input->recurrence?->endsOn?->format('Y-m-d'), 'recurrence_count' => $input->recurrence?->occurrenceCount,
            'reminder_minutes' => json_encode($input->reminderMinutes, JSON_THROW_ON_ERROR)];
    }

    /** @return array<string,mixed> */
    private function payload(MeetingInput $input): array
    {
        return $this->values($input);
    }

    private function mapMeeting(stdClass $row): MeetingRecord
    {
        $frequency = is_string($row->recurrence_frequency) ? $row->recurrence_frequency : null;

        return new MeetingRecord($this->requiredInt($row->id), $this->requiredString($row->public_id), $this->requiredString($row->series_public_id), $this->requiredInt($row->organizer_user_id),
            $this->requiredString($row->conversation_public_id), $this->requiredString($row->title), is_string($row->description) ? $row->description : null,
            new DateTimeImmutable($this->requiredString($row->starts_at)), new DateTimeImmutable($this->requiredString($row->ends_at)), MeetingMode::from($this->requiredString($row->mode)),
            is_string($row->location) ? $row->location : null,
            $frequency === null ? null : new MeetingRecurrence($frequency, $this->ints($row->recurrence_weekdays), is_string($row->recurrence_ends_on) ? new DateTimeImmutable($row->recurrence_ends_on) : null, is_numeric($row->recurrence_count) ? (int) $row->recurrence_count : null),
            MeetingStatus::from($this->requiredString($row->status)), $this->requiredInt($row->version));
    }

    private function mapInvitation(stdClass $row): MeetingInvitationRecord
    {
        return new MeetingInvitationRecord($this->requiredInt($row->id), $this->requiredInt($row->meeting_id), $this->requiredInt($row->user_id), MeetingRole::from($this->requiredString($row->role)), MeetingResponse::from($this->requiredString($row->response)), is_string($row->removed_at) ? $row->removed_at : null);
    }

    /** @return list<int> */
    private function ints(mixed $value): array
    {
        $items = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): int => is_numeric($item) ? (int) $item : 0, $items));
    }

    /** @return array<string, mixed> */
    private function payloadArray(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        if (! is_array($decoded)) {
            return [];
        }

        $payload = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $payload[$key] = $item;
            }
        }

        return $payload;
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new \UnexpectedValueException('Meeting persistence returned a non-string value.');
        }

        return $value;
    }

    private function requiredInt(mixed $value): int
    {
        if (! is_int($value) && ! is_numeric($value)) {
            throw new \UnexpectedValueException('Meeting persistence returned a non-integer value.');
        }

        return (int) $value;
    }
}
