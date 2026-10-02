<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\MeetingRecordingStore;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecording;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecordingSegment;
use App\Modules\Optional\Chat\Application\DTOs\MeetingRecordingShare;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingSegmentStatus;
use App\Modules\Optional\Chat\Domain\Meetings\MeetingRecordingStatus;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Symfony\Component\Uid\Ulid;

final readonly class DatabaseMeetingRecordingStore implements MeetingRecordingStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function forOccurrence(int $occurrenceId, bool $forUpdate = false): ?MeetingRecording
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)
            ->where('occurrence_id', $occurrenceId)
            ->latest('id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->recording($row) : null;
    }

    public function find(string $publicId, bool $forUpdate = false): ?MeetingRecording
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('public_id', $publicId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->recording($row) : null;
    }

    public function create(int $occurrenceId, int $initiatedByUserId): MeetingRecording
    {
        $id = (int) $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->insertGetId([
            'public_id' => (string) new Ulid,
            'occurrence_id' => $occurrenceId,
            'initiated_by_user_id' => $initiatedByUserId,
            'status' => MeetingRecordingStatus::Starting->value,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->find($id);

        return $this->recording($row instanceof stdClass ? $row : throw new \RuntimeException('Meeting recording is missing.'));
    }

    public function nextSegmentSequence(int $recordingId): int
    {
        $maximum = $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)
            ->where('recording_id', $recordingId)->max('sequence');

        return (is_numeric($maximum) ? (int) $maximum : 0) + 1;
    }

    public function setStatus(int $recordingId, MeetingRecordingStatus $status, ?string $failureCode = null): void
    {
        $values = ['status' => $status->value, 'failure_code' => $failureCode, 'updated_at' => now()];
        if ($status === MeetingRecordingStatus::Processing) {
            $values['ended_at'] = now();
        }
        $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('id', $recordingId)->update($values);
    }

    public function createSegment(int $recordingId, string $egressId, string $stagingPath): MeetingRecordingSegment
    {
        $sequence = $this->nextSegmentSequence($recordingId);
        $id = (int) $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)->insertGetId([
            'recording_id' => $recordingId,
            'sequence' => $sequence,
            'egress_id' => $egressId,
            'staging_path' => $stagingPath,
            'status' => MeetingRecordingSegmentStatus::Recording->value,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $row = $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)->find($id);

        return $this->segment($row instanceof stdClass ? $row : throw new \RuntimeException('Meeting recording segment is missing.'));
    }

    public function activeSegment(int $recordingId, bool $forUpdate = false): ?MeetingRecordingSegment
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)
            ->where('recording_id', $recordingId)
            ->whereIn('status', [MeetingRecordingSegmentStatus::Starting->value, MeetingRecordingSegmentStatus::Recording->value, MeetingRecordingSegmentStatus::Stopping->value])
            ->latest('sequence');
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row instanceof stdClass ? $this->segment($row) : null;
    }

    public function setSegmentStatus(int $segmentId, MeetingRecordingSegmentStatus $status): void
    {
        $values = ['status' => $status->value, 'updated_at' => now()];
        if (in_array($status, [MeetingRecordingSegmentStatus::Processing, MeetingRecordingSegmentStatus::Ready, MeetingRecordingSegmentStatus::Failed], true)) {
            $values['ended_at'] = now();
        }
        $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)->where('id', $segmentId)->update($values);
    }

    public function awaitingFinalization(int $limit): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)
            ->where('status', MeetingRecordingStatus::Processing->value)
            ->orderBy('id')->limit($limit)->get()
            ->map(fn (stdClass $row): MeetingRecording => $this->recording($row))->all());
    }

    public function segments(int $recordingId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)
            ->where('recording_id', $recordingId)->orderBy('sequence')->get()
            ->map(fn (stdClass $row): MeetingRecordingSegment => $this->segment($row))->all());
    }

    public function markReady(int $recordingId, string $filePublicId, int $durationSeconds): void
    {
        $updated = $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('id', $recordingId)->where('status', MeetingRecordingStatus::Processing->value)->update([
            'status' => MeetingRecordingStatus::Ready->value,
            'file_public_id' => $filePublicId,
            'duration_seconds' => $durationSeconds,
            'failure_code' => null,
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw new \RuntimeException('Meeting recording is no longer awaiting finalization.');
        }
        $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SEGMENTS)->where('recording_id', $recordingId)->update([
            'status' => MeetingRecordingSegmentStatus::Ready->value,
            'updated_at' => now(),
        ]);
    }

    public function participantHasAccess(int $recordingId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS.' as recordings')
            ->join(ChatDatabaseTable::MEETING_OCCURRENCES.' as occurrences', 'occurrences.id', '=', 'recordings.occurrence_id')
            ->join(ChatDatabaseTable::MEETING_INVITATIONS.' as invitations', 'invitations.meeting_id', '=', 'occurrences.meeting_id')
            ->where('recordings.id', $recordingId)->where('invitations.user_id', $userId)->whereNull('invitations.removed_at')
            ->whereNotExists(function (Builder $query) use ($userId): void {
                $query->selectRaw('1')->from(ChatDatabaseTable::MEETING_RTC_PARTICIPANTS.' as denied')
                    ->whereColumn('denied.occurrence_id', 'recordings.occurrence_id')->where('denied.user_id', $userId)->whereNotNull('denied.banned_at');
            })->exists();
    }

    public function sharedRecipientHasAccess(int $recordingId, int $userId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)
            ->where('recording_id', $recordingId)->where('recipient_user_id', $userId)->whereNull('revoked_at')->exists();
    }

    public function share(int $recordingId, int $recipientUserId, int $sharedByUserId): MeetingRecordingShare
    {
        $id = (int) $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)->insertGetId([
            'public_id' => (string) new Ulid, 'recording_id' => $recordingId, 'recipient_user_id' => $recipientUserId,
            'shared_by_user_id' => $sharedByUserId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)->find($id);

        return $this->shareRecord($row instanceof stdClass ? $row : throw new \RuntimeException('Meeting recording share is missing.'));
    }

    public function revokeShare(string $sharePublicId, int $sharedByUserId): bool
    {
        return $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)
            ->where('public_id', $sharePublicId)->where('shared_by_user_id', $sharedByUserId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]) === 1;
    }

    public function shares(int $recordingId): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)
            ->where('recording_id', $recordingId)->whereNull('revoked_at')->orderBy('id')->get()
            ->map(fn (stdClass $row): MeetingRecordingShare => $this->shareRecord($row))->all());
    }

    public function expiredReady(DateTimeImmutable $cutoff, int $limit): array
    {
        return array_values($this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)
            ->where('status', MeetingRecordingStatus::Ready->value)->where('ended_at', '<=', $cutoff->format(DATE_ATOM))
            ->orderBy('ended_at')->limit($limit)->get()->map(fn (stdClass $row): MeetingRecording => $this->recording($row))->all());
    }

    public function markRemovedByRetention(int $recordingId): void
    {
        $this->database->table(ChatDatabaseTable::MEETING_RECORDING_SHARES)->where('recording_id', $recordingId)->delete();
        if (Schema::hasTable(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)) {
            $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('recording_id', $recordingId)->delete();
        }
        $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('id', $recordingId)->update([
            'status' => MeetingRecordingStatus::Removed->value,
            'file_public_id' => null,
            'retention_removed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function retentionSummary(?DateTimeImmutable $cutoff): array
    {
        $query = $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('status', MeetingRecordingStatus::Ready->value);
        if ($cutoff !== null) {
            $query->where('ended_at', '<=', $cutoff->format(DATE_ATOM));
        }

        return [
            'ready' => $query->count(),
            'bytes' => 0,
            'oldestEndedAt' => is_string($oldest = (clone $query)->min('ended_at')) ? $oldest : null,
        ];
    }

    public function recordingRetentionDays(): ?int
    {
        $value = $this->database->table(ChatDatabaseTable::SETTINGS)->where('key', 'recording_retention_days')->value('value');
        if (! is_string($value)) {
            return null;
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_int($decoded) && $decoded > 0 ? $decoded : null;
    }

    public function hasRecordingRetentionSetting(): bool
    {
        return $this->database->table(ChatDatabaseTable::SETTINGS)->where('key', 'recording_retention_days')->exists();
    }

    public function setRecordingRetentionDays(?int $days): void
    {
        $this->database->table(ChatDatabaseTable::SETTINGS)->updateOrInsert(
            ['key' => 'recording_retention_days'],
            ['value' => json_encode($days, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function recording(stdClass $row): MeetingRecording
    {
        return new MeetingRecording(
            $this->requiredInt($row->id),
            $this->requiredString($row->public_id),
            $this->requiredInt($row->occurrence_id),
            $this->requiredInt($row->initiated_by_user_id),
            MeetingRecordingStatus::from($this->requiredString($row->status)),
            is_string($row->file_public_id) ? $row->file_public_id : null,
            is_string($row->started_at) ? $row->started_at : null,
            is_string($row->ended_at) ? $row->ended_at : null,
            is_numeric($row->duration_seconds) ? (int) $row->duration_seconds : null,
            is_string($row->retention_removed_at) ? $row->retention_removed_at : null,
            is_string($row->failure_code) ? $row->failure_code : null,
        );
    }

    private function segment(stdClass $row): MeetingRecordingSegment
    {
        return new MeetingRecordingSegment(
            $this->requiredInt($row->id),
            $this->requiredInt($row->recording_id),
            $this->requiredInt($row->sequence),
            $this->requiredString($row->egress_id),
            $this->requiredString($row->staging_path),
            MeetingRecordingSegmentStatus::from($this->requiredString($row->status)),
            is_string($row->started_at) ? $row->started_at : null,
            is_string($row->ended_at) ? $row->ended_at : null,
        );
    }

    private function shareRecord(stdClass $row): MeetingRecordingShare
    {
        return new MeetingRecordingShare(
            $this->requiredInt($row->id), $this->requiredString($row->public_id), $this->requiredInt($row->recording_id),
            $this->requiredInt($row->recipient_user_id), $this->requiredInt($row->shared_by_user_id),
            is_string($row->revoked_at) ? $row->revoked_at : null,
        );
    }

    private function requiredInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \RuntimeException('Expected numeric Meeting recording persistence value.');
    }

    private function requiredString(mixed $value): string
    {
        return is_string($value) ? $value : throw new \RuntimeException('Expected string Meeting recording persistence value.');
    }
}
