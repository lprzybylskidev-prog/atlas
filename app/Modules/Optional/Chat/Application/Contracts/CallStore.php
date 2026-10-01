<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\CallParticipantRecord;
use App\Modules\Optional\Chat\Application\DTOs\CallPreferences;
use App\Modules\Optional\Chat\Application\DTOs\CallRecord;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantRole;
use App\Modules\Optional\Chat\Domain\Calls\CallParticipantState;
use App\Modules\Optional\Chat\Domain\Calls\CallStatus;

interface CallStore
{
    public function lockKey(string $key): void;

    public function findByPublicId(string $publicId, bool $lock = false): ?CallRecord;

    public function findById(int $id): ?CallRecord;

    public function findActiveForConversation(int $conversationId, bool $lock = false): ?CallRecord;

    public function findByRequest(int $userId, string $clientRequestKey): ?CallRecord;

    public function create(int $conversationId, int $startedByUserId, string $clientRequestKey, string $requestHash, bool $cameraEnabled): CallRecord;

    public function setStatus(int $callId, CallStatus $status, bool $answered = false, bool $ended = false): void;

    public function participant(int $callId, int $userId, bool $lock = false): ?CallParticipantRecord;

    /** @return list<CallParticipantRecord> */
    public function participants(int $callId, bool $lock = false): array;

    public function addParticipant(int $callId, int $userId, CallParticipantRole $role, CallParticipantState $state, bool $cameraEnabled = false, bool $microphoneEnabled = false): CallParticipantRecord;

    public function setParticipantState(int $participantId, CallParticipantState $state, bool $cameraEnabled = false, bool $microphoneEnabled = false): void;

    public function setParticipantMedia(int $participantId, bool $cameraEnabled, bool $microphoneEnabled): void;

    public function setScreenShare(int $participantId, bool $active): bool;

    public function activeJoinedForUser(int $userId, bool $lock = false): ?CallParticipantRecord;

    public function activeParticipantForUser(int $userId): ?CallParticipantRecord;

    /** @return list<array{call: CallRecord, participant: CallParticipantRecord}> */
    public function historyForUser(int $userId): array;

    public function preferences(int $userId): CallPreferences;

    public function savePreferences(int $userId, CallPreferences $preferences): void;
}
