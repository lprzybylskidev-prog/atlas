<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInput;
use App\Modules\Optional\Chat\Application\DTOs\MeetingInvitationRecord;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecord;
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
