<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\ConversationStore;
use App\Modules\Optional\Chat\Application\DTOs\ConversationMembershipRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationRecord;
use App\Modules\Optional\Chat\Application\DTOs\ConversationTimelineEntry;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationMemberRole;
use App\Modules\Optional\Chat\Domain\Conversations\ConversationType;
use App\Modules\Optional\Chat\Domain\Conversations\MeetingResponse;
use App\Modules\Optional\Chat\Domain\Conversations\TimelineEntryType;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;
use UnexpectedValueException;

final readonly class DatabaseConversationStore implements ConversationStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function lockCanonicalKey(string $key): void
    {
        $this->database->select('select pg_advisory_xact_lock(hashtext(?))', [$key]);
    }

    public function findByPublicId(string $publicId, bool $lock = false): ?ConversationRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CONVERSATIONS)->where('public_id', $publicId);

        return $this->conversation($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function findDirect(int $lowerUserId, int $higherUserId): ?ConversationRecord
    {
        $row = $this->database->table(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS.' as pairs')
            ->join(ChatDatabaseTable::CONVERSATIONS.' as conversations', 'pairs.conversation_id', '=', 'conversations.id')
            ->where('pairs.lower_user_id', $lowerUserId)
            ->where('pairs.higher_user_id', $higherUserId)
            ->first(['conversations.*']);

        return $this->conversation($row);
    }

    public function findTeam(string $teamPublicId): ?ConversationRecord
    {
        return $this->conversation($this->database->table(ChatDatabaseTable::CONVERSATIONS)
            ->where('type', ConversationType::Team->value)
            ->where('team_public_id', $teamPublicId)
            ->first());
    }

    public function findMeeting(string $meetingOwnerKey): ?ConversationRecord
    {
        return $this->conversation($this->database->table(ChatDatabaseTable::CONVERSATIONS)
            ->where('type', ConversationType::Meeting->value)
            ->where('meeting_owner_key', $meetingOwnerKey)
            ->first());
    }

    public function create(ConversationType $type, ?string $name, ?string $teamPublicId, ?string $meetingOwnerKey, bool $systemOwned): ConversationRecord
    {
        $publicId = (string) Str::ulid();
        $now = now();
        $id = $this->database->table(ChatDatabaseTable::CONVERSATIONS)->insertGetId([
            'public_id' => $publicId,
            'type' => $type->value,
            'name' => $name,
            'avatar_file_public_id' => null,
            'team_public_id' => $teamPublicId,
            'meeting_owner_key' => $meetingOwnerKey,
            'system_owned' => $systemOwned,
            'closed_at' => null,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new ConversationRecord($id, $publicId, $type, $name, $teamPublicId, $meetingOwnerKey, $systemOwned, false, 1);
    }

    public function claimDirectPair(int $conversationId, int $lowerUserId, int $higherUserId): bool
    {
        return $this->database->table(ChatDatabaseTable::DIRECT_CONVERSATION_PAIRS)->insertOrIgnore([
            'lower_user_id' => $lowerUserId,
            'higher_user_id' => $higherUserId,
            'conversation_id' => $conversationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function discardUnclaimed(int $conversationId): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATIONS)->where('id', $conversationId)->delete();
    }

    public function activeMembership(int $conversationId, int $userId, bool $lock = false): ?ConversationMembershipRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('ended_at');

        return $this->membership($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function activeMemberships(int $conversationId, bool $lock = false): array
    {
        $query = $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $conversationId)
            ->whereNull('ended_at')
            ->orderBy('id');
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        $memberships = [];

        foreach ($rows as $row) {
            $membership = $this->membership($row);

            if ($membership !== null) {
                $memberships[] = $membership;
            }
        }

        return $memberships;
    }

    public function addMembership(int $conversationId, int $userId, ConversationMemberRole $role, ConversationType $source, ?MeetingResponse $meetingResponse = null): bool
    {
        $now = now();

        return $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => $role->value,
            'source' => $source->value,
            'meeting_response' => $meetingResponse?->value,
            'joined_at' => $now,
            'ended_at' => null,
            'ended_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }

    public function endMembership(int $membershipId, string $reason): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('id', $membershipId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'ended_reason' => $reason, 'updated_at' => now()]);
    }

    public function changeRole(int $membershipId, ConversationMemberRole $role): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('id', $membershipId)
            ->whereNull('ended_at')
            ->update(['role' => $role->value, 'updated_at' => now()]);
    }

    public function changeMeetingResponse(int $membershipId, MeetingResponse $response): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('id', $membershipId)
            ->whereNull('ended_at')
            ->update(['meeting_response' => $response->value, 'updated_at' => now()]);
    }

    public function updateGroupMetadata(int $conversationId, string $name, ?string $avatarFilePublicId): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATIONS)->where('id', $conversationId)->update([
            'name' => $name,
            'avatar_file_public_id' => $avatarFilePublicId,
            'version' => $this->database->raw('version + 1'),
            'updated_at' => now(),
        ]);
    }

    public function close(int $conversationId): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATIONS)
            ->where('id', $conversationId)
            ->whereNull('closed_at')
            ->update(['closed_at' => now(), 'version' => $this->database->raw('version + 1'), 'updated_at' => now()]);
    }

    public function appendTimeline(int $conversationId, TimelineEntryType $type, ?int $actorUserId = null, ?int $subjectUserId = null, array $metadata = []): void
    {
        $this->database->table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)->insert([
            'public_id' => (string) Str::ulid(),
            'conversation_id' => $conversationId,
            'type' => $type->value,
            'actor_user_id' => $actorUserId,
            'subject_user_id' => $subjectUserId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function timeline(int $conversationId): array
    {
        $entries = [];

        foreach ($this->database->table(ChatDatabaseTable::CONVERSATION_TIMELINE_ENTRIES)
            ->where('conversation_id', $conversationId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get() as $row) {
            $values = get_object_vars($row);
            $metadata = json_decode(is_string($values['metadata'] ?? null) ? $values['metadata'] : '{}', true, flags: JSON_THROW_ON_ERROR);
            $entries[] = new ConversationTimelineEntry(
                publicId: $this->string($values, 'public_id'),
                type: TimelineEntryType::from($this->string($values, 'type')),
                actorUserId: $this->nullableInt($values['actor_user_id'] ?? null),
                subjectUserId: $this->nullableInt($values['subject_user_id'] ?? null),
                metadata: is_array($metadata) ? $this->scalarMetadata($metadata) : [],
                occurredAt: $this->string($values, 'occurred_at'),
            );
        }

        return $entries;
    }

    public function hasActiveMembership(int $conversationId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::CONVERSATION_MEMBERSHIPS)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists();
    }

    private function conversation(?object $row): ?ConversationRecord
    {
        if (! $row instanceof stdClass) {
            return null;
        }

        $values = get_object_vars($row);

        return new ConversationRecord(
            id: $this->int($values, 'id'),
            publicId: $this->string($values, 'public_id'),
            type: ConversationType::from($this->string($values, 'type')),
            name: $this->nullableString($values['name'] ?? null),
            teamPublicId: $this->nullableString($values['team_public_id'] ?? null),
            meetingOwnerKey: $this->nullableString($values['meeting_owner_key'] ?? null),
            systemOwned: (bool) ($values['system_owned'] ?? false),
            closed: ($values['closed_at'] ?? null) !== null,
            version: $this->int($values, 'version'),
        );
    }

    private function membership(?object $row): ?ConversationMembershipRecord
    {
        if (! $row instanceof stdClass) {
            return null;
        }

        $values = get_object_vars($row);
        $response = $this->nullableString($values['meeting_response'] ?? null);

        return new ConversationMembershipRecord(
            id: $this->int($values, 'id'),
            publicId: $this->string($values, 'public_id'),
            userId: $this->int($values, 'user_id'),
            role: ConversationMemberRole::from($this->string($values, 'role')),
            meetingResponse: $response === null ? null : MeetingResponse::from($response),
        );
    }

    /** @param array<mixed> $values */
    private function int(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_numeric($value)) {
            throw new UnexpectedValueException(sprintf('Chat persistence field [%s] must be an integer.', $key));
        }

        return (int) $value;
    }

    /** @param array<mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_scalar($value) || (string) $value === '') {
            throw new UnexpectedValueException(sprintf('Chat persistence field [%s] must be a non-empty string.', $key));
        }

        return (string) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, scalar|null>
     */
    private function scalarMetadata(array $values): array
    {
        $metadata = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }
}
