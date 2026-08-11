<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use App\Shared\Application\ManagedProcesses\DTOs\ManagedProcessRunSummary;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class DatabaseManagedProcessRunInspector implements ManagedProcessRunInspector
{
    public function __construct(private ConnectionInterface $database) {}

    public function internalIdForPublicId(string $runPublicId): ?int
    {
        $id = $this->database
            ->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $runPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function recentRunsForProcess(string $processKey, int $limit): array
    {
        return array_values($this->database
            ->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('process_key', $processKey)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get(['public_id', 'status', 'current_stage', 'progress_current', 'progress_total', 'progress_label', 'created_at', 'started_at', 'finished_at'])
            ->map(static fn (object $row): ManagedProcessRunSummary => new ManagedProcessRunSummary(
                publicId: self::string($row->public_id ?? null) ?? '',
                status: self::string($row->status ?? null) ?? '',
                currentStage: self::string($row->current_stage ?? null),
                progressCurrent: self::int($row->progress_current ?? null),
                progressTotal: self::nullableInt($row->progress_total ?? null),
                progressLabel: self::string($row->progress_label ?? null),
                createdAt: self::string($row->created_at ?? null),
                startedAt: self::string($row->started_at ?? null),
                finishedAt: self::string($row->finished_at ?? null),
            ))
            ->all());
    }

    public function inputSnapshot(string $runPublicId): array
    {
        $snapshot = $this->database
            ->table(ManagedProcessesDatabaseTable::RUNS)
            ->where('public_id', $runPublicId)
            ->value('input_snapshot');

        if (! is_string($snapshot) || $snapshot === '') {
            return [];
        }

        $decoded = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Managed process input snapshot must decode to a JSON object.');
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
