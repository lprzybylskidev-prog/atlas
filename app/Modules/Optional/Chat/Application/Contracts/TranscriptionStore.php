<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use App\Modules\Optional\Chat\Application\DTOs\MeetingTranscription;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionResult;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptShare;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptVersion;
use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;

interface TranscriptionStore
{
    public function forRecording(int $recordingId, bool $forUpdate = false): ?MeetingTranscription;

    public function find(string $publicId, bool $forUpdate = false): ?MeetingTranscription;

    public function create(int $recordingId, int $requestedByUserId, string $providerKey): MeetingTranscription;

    public function queue(int $transcriptionId, string $runPublicId): void;

    public function prepareRetry(int $transcriptionId, string $dispatchToken, string $providerKey): void;

    public function submitted(int $transcriptionId, string $externalJobId): void;

    public function processing(int $transcriptionId): void;

    public function incrementAttempts(int $transcriptionId): int;

    public function complete(int $transcriptionId, TranscriptionResult $result): void;

    public function fail(int $transcriptionId, string $failureCode): void;

    public function setStatus(int $transcriptionId, TranscriptionStatus $status): void;

    public function edit(int $transcriptionId, int $expectedVersion, int $editorUserId, string $text): void;

    /** @return list<TranscriptVersion> */
    public function versions(int $transcriptionId): array;

    public function participantHasAccess(int $transcriptionId, int $userId): bool;

    public function sharedRecipientHasAccess(int $transcriptionId, int $userId): bool;

    public function share(int $transcriptionId, int $recipientUserId, int $sharedByUserId): TranscriptShare;

    public function revokeShare(string $sharePublicId, int $sharedByUserId): bool;

    /** @return list<TranscriptShare> */
    public function shares(int $transcriptionId): array;
}
