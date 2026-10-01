<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\CallStore;
use App\Modules\Optional\Chat\Application\DTOs\CallParticipantRecord;
use App\Modules\Optional\Chat\Application\DTOs\CallPreferences;
use App\Modules\Optional\Chat\Application\DTOs\CallRecord;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantRole;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;
use App\Modules\Optional\Chat\Domain\Calls\CallStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use stdClass;
use UnexpectedValueException;

final readonly class DatabaseCallStore implements CallStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function lockKey(string $key): void
    {
        $this->database->select('select pg_advisory_xact_lock(hashtext(?))', [$key]);
    }

    public function findByPublicId(string $publicId, bool $lock = false): ?CallRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CALLS)->where('public_id', $publicId);

        return $this->call($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function findById(int $id): ?CallRecord
    {
        return $this->call($this->database->table(ChatDatabaseTable::CALLS)->where('id', $id)->first());
    }

    public function findActiveForConversation(int $conversationId, bool $lock = false): ?CallRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CALLS)
            ->where('conversation_id', $conversationId)
            ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value])
            ->orderByDesc('id');

        return $this->call($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function findByRequest(int $userId, string $clientRequestKey): ?CallRecord
    {
        return $this->call($this->database->table(ChatDatabaseTable::CALLS)
            ->where('started_by_user_id', $userId)
            ->where('client_request_key', $clientRequestKey)
            ->first());
    }

    public function create(int $conversationId, int $startedByUserId, string $clientRequestKey, string $requestHash, bool $cameraEnabled): CallRecord
    {
        $publicId = (string) Str::ulid();
        $now = now();
        $id = $this->database->table(ChatDatabaseTable::CALLS)->insertGetId([
            'public_id' => $publicId,
            'conversation_id' => $conversationId,
            'started_by_user_id' => $startedByUserId,
            'client_request_key' => $clientRequestKey,
            'request_hash' => $requestHash,
            'room_name' => 'atlas-call-'.strtolower($publicId),
            'initial_camera_enabled' => $cameraEnabled,
            'status' => CallStatus::Ringing->value,
            'started_at' => $now,
            'answered_at' => null,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId) ?? throw new UnexpectedValueException('Created Call could not be reloaded.');
    }

    public function setStatus(int $callId, CallStatus $status, bool $answered = false, bool $ended = false): void
    {
        $values = ['status' => $status->value, 'updated_at' => now()];

        if ($answered) {
            $values['answered_at'] = now();
        }

        if ($ended) {
            $values['ended_at'] = now();
        }

        $this->database->table(ChatDatabaseTable::CALLS)->where('id', $callId)->update($values);
    }

    public function participant(int $callId, int $userId, bool $lock = false): ?CallParticipantRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)
            ->where('call_id', $callId)
            ->where('user_id', $userId);

        return $this->participantRecord($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function participants(int $callId, bool $lock = false): array
    {
        $query = $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)
            ->where('call_id', $callId)
            ->orderBy('id');
        $participants = [];

        foreach (($lock ? $query->lockForUpdate() : $query)->get() as $row) {
            $participant = $this->participantRecord($row);

            if ($participant !== null) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }

    public function addParticipant(int $callId, int $userId, CallParticipantRole $role, CallParticipantState $state, bool $cameraEnabled = false, bool $microphoneEnabled = false): CallParticipantRecord
    {
        $now = now();
        $id = $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'call_id' => $callId,
            'user_id' => $userId,
            'role' => $role->value,
            'state' => $state->value,
            'camera_enabled' => $cameraEnabled,
            'microphone_enabled' => $microphoneEnabled,
            'joined_at' => $state === CallParticipantState::Joined ? $now : null,
            'left_at' => null,
            'screen_share_started_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->participantRecord($this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)->where('id', $id)->first())
            ?? throw new UnexpectedValueException('Created Call participant could not be reloaded.');
    }

    public function setParticipantState(int $participantId, CallParticipantState $state, bool $cameraEnabled = false, bool $microphoneEnabled = false): void
    {
        $joined = $state === CallParticipantState::Joined;
        $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)->where('id', $participantId)->update([
            'state' => $state->value,
            'camera_enabled' => $joined && $cameraEnabled,
            'microphone_enabled' => $joined && $microphoneEnabled,
            'joined_at' => $joined ? now() : $this->database->raw('joined_at'),
            'left_at' => $joined ? null : now(),
            'screen_share_started_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function setParticipantMedia(int $participantId, bool $cameraEnabled, bool $microphoneEnabled): void
    {
        $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)
            ->where('id', $participantId)
            ->where('state', CallParticipantState::Joined->value)
            ->update([
                'camera_enabled' => $cameraEnabled,
                'microphone_enabled' => $microphoneEnabled,
                'updated_at' => now(),
            ]);
    }

    public function setScreenShare(int $participantId, bool $active): bool
    {
        try {
            return $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS)
                ->where('id', $participantId)
                ->where('state', CallParticipantState::Joined->value)
                ->update([
                    'screen_share_started_at' => $active ? now() : null,
                    'updated_at' => now(),
                ]) === 1;
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                return false;
            }

            throw $exception;
        }
    }

    public function activeJoinedForUser(int $userId, bool $lock = false): ?CallParticipantRecord
    {
        $query = $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS.' as participants')
            ->join(ChatDatabaseTable::CALLS.' as calls', 'calls.id', '=', 'participants.call_id')
            ->where('participants.user_id', $userId)
            ->where('participants.state', CallParticipantState::Joined->value)
            ->whereIn('calls.status', [CallStatus::Ringing->value, CallStatus::Active->value])
            ->select('participants.*');

        return $this->participantRecord($lock ? $query->lockForUpdate()->first() : $query->first());
    }

    public function activeParticipantForUser(int $userId): ?CallParticipantRecord
    {
        return $this->participantRecord($this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS.' as participants')
            ->join(ChatDatabaseTable::CALLS.' as calls', 'calls.id', '=', 'participants.call_id')
            ->where('participants.user_id', $userId)
            ->whereIn('participants.state', [
                CallParticipantState::Joined->value,
                CallParticipantState::Ringing->value,
                CallParticipantState::Notified->value,
                CallParticipantState::Left->value,
                CallParticipantState::Failed->value,
            ])
            ->whereIn('calls.status', [CallStatus::Ringing->value, CallStatus::Active->value])
            ->orderByRaw("case participants.state when 'joined' then 0 when 'ringing' then 1 when 'notified' then 2 else 3 end")
            ->orderByDesc('calls.id')
            ->select('participants.*')
            ->first());
    }

    public function historyForUser(int $userId): array
    {
        $history = [];
        $rows = $this->database->table(ChatDatabaseTable::CALL_PARTICIPANTS.' as participants')
            ->join(ChatDatabaseTable::CALLS.' as calls', 'calls.id', '=', 'participants.call_id')
            ->where('participants.user_id', $userId)
            ->orderByDesc('calls.started_at')
            ->orderByDesc('calls.id')
            ->get(['calls.*', 'participants.id as participant_row_id', 'participants.public_id as participant_public_id', 'participants.user_id as participant_user_id', 'participants.role as participant_role', 'participants.state as participant_state', 'participants.camera_enabled as participant_camera_enabled', 'participants.microphone_enabled as participant_microphone_enabled', 'participants.joined_at as participant_joined_at', 'participants.left_at as participant_left_at', 'participants.screen_share_started_at as participant_screen_share_started_at']);

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $history[] = [
                'call' => $this->call($row) ?? throw new UnexpectedValueException('Call history row is invalid.'),
                'participant' => $this->participantFromValues([
                    'id' => $values['participant_row_id'] ?? null,
                    'public_id' => $values['participant_public_id'] ?? null,
                    'call_id' => $values['id'] ?? null,
                    'user_id' => $values['participant_user_id'] ?? null,
                    'role' => $values['participant_role'] ?? null,
                    'state' => $values['participant_state'] ?? null,
                    'camera_enabled' => $values['participant_camera_enabled'] ?? false,
                    'microphone_enabled' => $values['participant_microphone_enabled'] ?? false,
                    'joined_at' => $values['participant_joined_at'] ?? null,
                    'left_at' => $values['participant_left_at'] ?? null,
                    'screen_share_started_at' => $values['participant_screen_share_started_at'] ?? null,
                ]),
            ];
        }

        return $history;
    }

    public function preferences(int $userId): CallPreferences
    {
        $row = $this->database->table(ChatDatabaseTable::CALL_PREFERENCES)->where('user_id', $userId)->first();

        if (! $row instanceof stdClass) {
            return new CallPreferences;
        }

        $values = get_object_vars($row);

        return new CallPreferences(
            cameraDeviceId: $this->nullableString($values['camera_device_id'] ?? null),
            microphoneDeviceId: $this->nullableString($values['microphone_device_id'] ?? null),
            speakerDeviceId: $this->nullableString($values['speaker_device_id'] ?? null),
            outgoingCameraEnabled: (bool) ($values['outgoing_camera_enabled'] ?? false),
        );
    }

    public function savePreferences(int $userId, CallPreferences $preferences): void
    {
        $now = now();
        $this->database->table(ChatDatabaseTable::CALL_PREFERENCES)->upsert([[
            'user_id' => $userId,
            'camera_device_id' => $preferences->cameraDeviceId,
            'microphone_device_id' => $preferences->microphoneDeviceId,
            'speaker_device_id' => $preferences->speakerDeviceId,
            'outgoing_camera_enabled' => $preferences->outgoingCameraEnabled,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['user_id'], ['camera_device_id', 'microphone_device_id', 'speaker_device_id', 'outgoing_camera_enabled', 'updated_at']);
    }

    private function call(?object $row): ?CallRecord
    {
        if (! $row instanceof stdClass) {
            return null;
        }

        $values = get_object_vars($row);

        return new CallRecord(
            id: $this->int($values, 'id'),
            publicId: $this->string($values, 'public_id'),
            conversationId: $this->int($values, 'conversation_id'),
            startedByUserId: $this->int($values, 'started_by_user_id'),
            requestHash: $this->string($values, 'request_hash'),
            roomName: $this->string($values, 'room_name'),
            initialCameraEnabled: (bool) ($values['initial_camera_enabled'] ?? false),
            status: CallStatus::from($this->string($values, 'status')),
            startedAt: $this->string($values, 'started_at'),
            answeredAt: $this->nullableString($values['answered_at'] ?? null),
            endedAt: $this->nullableString($values['ended_at'] ?? null),
        );
    }

    private function participantRecord(?object $row): ?CallParticipantRecord
    {
        return $row instanceof stdClass ? $this->participantFromValues(get_object_vars($row)) : null;
    }

    /** @param array<mixed> $values */
    private function participantFromValues(array $values): CallParticipantRecord
    {
        return new CallParticipantRecord(
            id: $this->int($values, 'id'),
            publicId: $this->string($values, 'public_id'),
            callId: $this->int($values, 'call_id'),
            userId: $this->int($values, 'user_id'),
            role: CallParticipantRole::from($this->string($values, 'role')),
            state: CallParticipantState::from($this->string($values, 'state')),
            cameraEnabled: (bool) ($values['camera_enabled'] ?? false),
            microphoneEnabled: (bool) ($values['microphone_enabled'] ?? false),
            joinedAt: $this->nullableString($values['joined_at'] ?? null),
            leftAt: $this->nullableString($values['left_at'] ?? null),
            screenShareStartedAt: $this->nullableString($values['screen_share_started_at'] ?? null),
        );
    }

    /** @param array<mixed> $values */
    private function int(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_numeric($value)) {
            throw new UnexpectedValueException(sprintf('Call persistence field [%s] must be an integer.', $key));
        }

        return (int) $value;
    }

    /** @param array<mixed> $values */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_scalar($value) || (string) $value === '') {
            throw new UnexpectedValueException(sprintf('Call persistence field [%s] must be a non-empty string.', $key));
        }

        return (string) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
