<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Lifecycle;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class FileDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private ConnectionInterface $db,
        private FileLifecycle $files,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        return new DataLifecyclePreview([
            new DataLifecycleImpact(
                dataSet: 'files.private_objects',
                estimatedRecords: $this->supports($subject, $operation) ? $this->liveFileCount($subject->identifier) : 0,
                irreversible: true,
                details: $this->supports($subject, $operation) ? $this->liveFiles($subject->identifier) : [],
            ),
        ]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        if (! $this->supports($subject, $operation)) {
            return new DataLifecycleResult([
                new DataLifecycleStepResult('files.private_objects_skipped', 0, true),
            ]);
        }

        $result = match ($operation) {
            DataLifecycleOperation::Delete => $this->files->delete($subject->identifier, reason: $correlationId),
            DataLifecycleOperation::Anonymize => $this->files->anonymize($subject->identifier, reason: $correlationId),
        };

        return new DataLifecycleResult([
            new DataLifecycleStepResult(
                step: $operation === DataLifecycleOperation::Delete
                    ? 'files.private_objects_deleted'
                    : 'files.private_objects_anonymized',
                affectedRecords: $result->completed ? 1 : 0,
                idempotent: true,
            ),
        ]);
    }

    private function supports(DataLifecycleSubject $subject, DataLifecycleOperation $operation): bool
    {
        return in_array($subject->type, ['file', 'file_object'], true)
            && in_array($operation, [DataLifecycleOperation::Delete, DataLifecycleOperation::Anonymize], true);
    }

    private function liveFileCount(string $publicId): int
    {
        return $this->db->table(DatabaseTable::FILE_OBJECTS)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function liveFiles(string $publicId): array
    {
        $records = [];

        foreach ($this->db->table(DatabaseTable::FILE_OBJECTS)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->get([
                'id',
                'public_id',
                'disk',
                'path',
                'physical_owner',
                'original_name',
                'extension',
                'mime_type',
                'size_bytes',
                'scan_state',
                'available_at',
                'created_at',
                'updated_at',
            ]) as $record) {
            $records[] = $this->recordToArray($record);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(object $record): array
    {
        $row = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
