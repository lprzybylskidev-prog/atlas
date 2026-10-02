<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\TranscriptionStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingTranscription;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptionResult;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptShare;
use App\Modules\Optional\Chat\Application\DTOs\TranscriptVersion;
use App\Modules\Optional\Chat\Domain\Meetings\TranscriptionStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use RuntimeException;
use stdClass;
use Symfony\Component\Uid\Ulid;

final readonly class DatabaseTranscriptionStore implements TranscriptionStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function forRecording(int $recordingId, bool $forUpdate = false): ?MeetingTranscription
    {
        return $this->first($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('recording_id', $recordingId), $forUpdate);
    }

    public function find(string $publicId, bool $forUpdate = false): ?MeetingTranscription
    {
        return $this->first($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('public_id', $publicId), $forUpdate);
    }

    public function create(int $recordingId, int $requestedByUserId, string $providerKey): MeetingTranscription
    {
        $id = (int) $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->insertGetId([
            'public_id' => (string) new Ulid,
            'recording_id' => $recordingId,
            'requested_by_user_id' => $requestedByUserId,
            'status' => TranscriptionStatus::Queued->value,
            'provider_key' => $providerKey,
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->find($id);

        return $this->transcription($row instanceof stdClass ? $row : throw new RuntimeException('Meeting transcription is missing.'));
    }

    public function queue(int $transcriptionId, string $runPublicId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'managed_process_run_public_id' => $runPublicId,
            'updated_at' => now(),
        ]);
    }

    public function prepareRetry(int $transcriptionId, string $dispatchToken, string $providerKey): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'status' => TranscriptionStatus::Queued->value,
            'provider_job_id' => null,
            'managed_process_run_public_id' => $dispatchToken,
            'provider_key' => $providerKey,
            'attempt_count' => 0,
            'failure_code' => null,
            'submitted_at' => null,
            'failed_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function submitted(int $transcriptionId, string $externalJobId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'status' => TranscriptionStatus::Submitted->value,
            'provider_job_id' => $externalJobId,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function processing(int $transcriptionId): void
    {
        $this->setStatus($transcriptionId, TranscriptionStatus::Processing);
    }

    public function incrementAttempts(int $transcriptionId): int
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->increment('attempt_count', 1, ['updated_at' => now()]);

        return $this->integer($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->value('attempt_count'));
    }

    public function complete(int $transcriptionId, TranscriptionResult $result): void
    {
        $segments = array_map(static fn ($segment): array => $segment->toArray(), $result->segments);
        $maximumVersion = $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)
            ->where('transcription_id', $transcriptionId)->max('version');
        $nextVersion = ($maximumVersion === null ? 0 : $this->integer($maximumVersion)) + 1;
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)->insert([
            'public_id' => (string) new Ulid,
            'transcription_id' => $transcriptionId,
            'version' => $nextVersion,
            'source' => 'provider',
            'created_by_user_id' => null,
            'text' => $result->text,
            'segments' => $segments === [] ? null : json_encode($segments, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'status' => TranscriptionStatus::Completed->value,
            'current_text' => $result->text,
            'current_segments' => $segments === [] ? null : json_encode($segments, JSON_THROW_ON_ERROR),
            'failure_code' => null,
            'completed_at' => now(),
            'failed_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function fail(int $transcriptionId, string $failureCode): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'status' => TranscriptionStatus::Failed->value,
            'failure_code' => $failureCode,
            'failed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function setStatus(int $transcriptionId, TranscriptionStatus $status): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'status' => $status->value,
            'updated_at' => now(),
        ]);
    }

    public function edit(int $transcriptionId, int $expectedVersion, int $editorUserId, string $text): void
    {
        $actualVersion = $this->integer($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)
            ->where('transcription_id', $transcriptionId)->max('version'));
        if ($actualVersion !== $expectedVersion) {
            throw new RuntimeException('The transcript changed before this edit was saved.');
        }
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)->insert([
            'public_id' => (string) new Ulid,
            'transcription_id' => $transcriptionId,
            'version' => $actualVersion + 1,
            'source' => 'edit',
            'created_by_user_id' => $editorUserId,
            'text' => $text,
            'segments' => null,
            'created_at' => now(),
        ]);
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('id', $transcriptionId)->update([
            'current_text' => $text,
            'current_segments' => null,
            'updated_at' => now(),
        ]);
    }

    public function versions(int $transcriptionId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_VERSIONS)
            ->where('transcription_id', $transcriptionId)->orderBy('version')->get()
            ->map(fn (stdClass $row): TranscriptVersion => new TranscriptVersion(
                $this->string($row->public_id),
                $this->integer($row->version),
                $this->string($row->source),
                is_numeric($row->created_by_user_id) ? (int) $row->created_by_user_id : null,
                $this->string($row->text),
                $this->string($row->created_at),
            ))->all());
    }

    public function participantHasAccess(int $transcriptionId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS.' as transcriptions')
            ->join(ChatDatabaseTable::MEETING_RECORDINGS.' as recordings', 'recordings.id', '=', 'transcriptions.recording_id')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.id', '=', 'recordings.occurrence_id')
            ->join(ChatDatabaseTable::MEETING_INVITATIONS.' as invitations', 'invitations.meeting_id', '=', 'occurrences.meeting_id')
            ->where('transcriptions.id', $transcriptionId)->where('invitations.user_id', $userId)->whereNull('invitations.removed_at')
            ->whereNotExists(function (Builder $query) use ($userId): void {
                $query->selectRaw('1')->from(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS.' as denied')
                    ->whereColumn('denied.occurrence_id', 'recordings.occurrence_id')->where('denied.user_id', $userId)->whereNotNull('denied.banned_at');
            })->exists();
    }

    public function sharedRecipientHasAccess(int $transcriptionId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)
            ->where('transcription_id', $transcriptionId)->where('recipient_user_id', $userId)->whereNull('revoked_at')->exists();
    }

    public function share(int $transcriptionId, int $recipientUserId, int $sharedByUserId): TranscriptShare
    {
        $publicId = (string) new Ulid;
        $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)->insert([
            'public_id' => $publicId,
            'transcription_id' => $transcriptionId,
            'recipient_user_id' => $recipientUserId,
            'shared_by_user_id' => $sharedByUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new TranscriptShare($publicId, $recipientUserId, $sharedByUserId);
    }

    public function revokeShare(string $sharePublicId, int $sharedByUserId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)
            ->where('public_id', $sharePublicId)->where('shared_by_user_id', $sharedByUserId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) === 1;
    }

    public function shares(int $transcriptionId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPT_SHARES)
            ->where('transcription_id', $transcriptionId)->whereNull('revoked_at')->orderBy('id')->get()
            ->map(fn (stdClass $row): TranscriptShare => new TranscriptShare(
                $this->string($row->public_id),
                $this->integer($row->recipient_user_id),
                $this->integer($row->shared_by_user_id),
            ))->all());
    }

    private function first(Builder $query, bool $forUpdate): ?MeetingTranscription
    {
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->transcription($row) : null;
    }

    private function transcription(stdClass $row): MeetingTranscription
    {
        return new MeetingTranscription(
            $this->integer($row->id),
            $this->string($row->public_id),
            $this->integer($row->recording_id),
            $this->integer($row->requested_by_user_id),
            TranscriptionStatus::from($this->string($row->status)),
            $this->string($row->provider_key),
            is_string($row->provider_job_id) ? $row->provider_job_id : null,
            is_string($row->managed_process_run_public_id) ? $row->managed_process_run_public_id : null,
            $this->integer($row->attempt_count),
            is_string($row->current_text) ? $row->current_text : null,
            $this->segments($row->current_segments),
            is_string($row->failure_code) ? $row->failure_code : null,
            is_string($row->completed_at) ? $row->completed_at : null,
        );
    }

    /** @return list<array{text:string,startsAtMilliseconds:?int,endsAtMilliseconds:?int,speaker:?string}>|null */
    private function segments(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Expected transcript segments to decode to a list.');
        }

        $segments = [];
        foreach ($decoded as $segment) {
            if (! is_array($segment) || ! is_string($segment['text'] ?? null)) {
                throw new RuntimeException('Expected a valid persisted transcript segment.');
            }
            $startsAt = $segment['startsAtMilliseconds'] ?? null;
            $endsAt = $segment['endsAtMilliseconds'] ?? null;
            $speaker = $segment['speaker'] ?? null;
            $segments[] = [
                'text' => $segment['text'],
                'startsAtMilliseconds' => $startsAt === null ? null : $this->integer($startsAt),
                'endsAtMilliseconds' => $endsAt === null ? null : $this->integer($endsAt),
                'speaker' => $speaker === null ? null : $this->string($speaker),
            ];
        }

        return $segments;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new RuntimeException('Expected numeric transcription persistence value.');
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new RuntimeException('Expected string transcription persistence value.');
    }
}
