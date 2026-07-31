<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Lifecycle;

use App\Modules\Core\Exports\Application\Enums\ReportExportStatus;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleBlocker;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class ExportDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private ConnectionInterface $db,
        private FileLifecycle $files,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $impacts = array_values(array_filter([
            $this->impact('exports.requests', $this->matchingRequests($subject)->count(), $this->records($this->matchingRequests($subject), [
                'id',
                'public_id',
                'report_key',
                'module_key',
                'format',
                'team_id',
                'active_team_public_id',
                'requested_by_user_id',
                'requesting_user_public_id',
                'status',
                'estimated_row_count',
                'process_run_id',
                'queued_at',
                'started_at',
                'finished_at',
                'failed_at',
                'expires_at',
                'created_at',
            ])),
            $this->impact('exports.artifacts', $this->matchingArtifacts($subject)->count(), $this->records($this->matchingArtifacts($subject), [
                'artifacts.id as artifact_id',
                'artifacts.public_id as artifact_public_id',
                'artifacts.export_request_id',
                'artifacts.file_object_id',
                'artifacts.file_object_public_id',
                'artifacts.status',
                'artifacts.filename',
                'artifacts.content_type',
                'artifacts.size_bytes',
                'artifacts.created_by_user_id',
                'artifacts.available_at',
                'artifacts.failed_at',
                'artifacts.expires_at',
                'requests.public_id as export_request_public_id',
            ])),
            $this->impact('exports.render_credentials', $this->matchingRenderCredentials($subject)->count(), $this->records($this->matchingRenderCredentials($subject), [
                'credentials.id as credential_id',
                'credentials.public_id as credential_public_id',
                'credentials.export_request_id',
                'credentials.requested_by_user_id',
                'credentials.team_id',
                'credentials.module_key',
                'credentials.report_key',
                'credentials.expires_at',
                'credentials.consumed_at',
                'requests.public_id as export_request_public_id',
            ])),
        ]));
        $activeExports = $this->matchingRequests($subject)
            ->whereIn('status', $this->activeStatuses())
            ->count();
        $blockers = [];

        if ($activeExports > 0) {
            $blockers[] = new DataLifecycleBlocker(
                code: 'export_generation_active',
                message: 'Subject is referenced by an active export request.',
            );
        }

        return new DataLifecyclePreview($impacts, $blockers);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $activeExports = $this->matchingRequests($subject)
            ->whereIn('status', $this->activeStatuses())
            ->count();

        if ($activeExports > 0) {
            return new DataLifecycleResult([], [
                new DataLifecycleBlocker(
                    code: 'export_generation_active',
                    message: 'Subject is referenced by an active export request.',
                ),
            ]);
        }

        return new DataLifecycleResult([
            new DataLifecycleStepResult('exports.artifact_files_removed', $this->removeArtifactFiles($subject, $operation), true),
            new DataLifecycleStepResult('exports.artifacts_expired', $this->expireArtifacts($subject), true),
            new DataLifecycleStepResult('exports.render_credentials_removed', $this->matchingRenderCredentials($subject)->delete(), true),
            new DataLifecycleStepResult('exports.requests_redacted', $this->redactRequests($subject), true),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function impact(string $dataSet, int $records, array $details): ?DataLifecycleImpact
    {
        return $records > 0 ? new DataLifecycleImpact($dataSet, $records, true, $details) : null;
    }

    private function matchingRequests(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where(function (Builder $query) use ($subject): void {
                $query
                    ->where('public_id', $subject->identifier)
                    ->orWhere('active_team_public_id', $subject->identifier)
                    ->orWhere('requesting_user_public_id', $subject->identifier);

                $this->orJsonTextContains($query, 'filters', $subject->identifier);
                $this->orJsonTextContains($query, 'sorting', $subject->identifier);
                $this->orJsonTextContains($query, 'visible_columns', $subject->identifier);
                $this->orJsonTextContains($query, 'column_order', $subject->identifier);
                $this->orJsonTextContains($query, 'time_range', $subject->identifier);
                $this->orJsonTextContains($query, 'authorization_snapshot', $subject->identifier);
                $this->orTextContains($query, 'safe_error_summary', $subject->identifier);
            });
    }

    private function matchingArtifacts(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::REPORT_EXPORT_ARTIFACTS.' as artifacts')
            ->join(DatabaseTable::REPORT_EXPORT_REQUESTS.' as requests', 'requests.id', '=', 'artifacts.export_request_id')
            ->where(function (Builder $query) use ($subject): void {
                $query
                    ->where('artifacts.public_id', $subject->identifier)
                    ->orWhere('artifacts.file_object_public_id', $subject->identifier);

                $this->orTextContains($query, 'artifacts.filename', $subject->identifier);
                $this->orTextContains($query, 'requests.public_id', $subject->identifier);
                $this->orTextContains($query, 'requests.active_team_public_id', $subject->identifier);
                $this->orTextContains($query, 'requests.requesting_user_public_id', $subject->identifier);
                $this->orJsonTextContains($query, 'requests.filters', $subject->identifier);
                $this->orJsonTextContains($query, 'requests.authorization_snapshot', $subject->identifier);
            });
    }

    private function matchingRenderCredentials(DataLifecycleSubject $subject): Builder
    {
        return $this->db->table(DatabaseTable::REPORT_RENDER_CREDENTIALS.' as credentials')
            ->join(DatabaseTable::REPORT_EXPORT_REQUESTS.' as requests', 'requests.id', '=', 'credentials.export_request_id')
            ->where(function (Builder $query) use ($subject): void {
                $this->orTextContains($query, 'requests.public_id', $subject->identifier);
                $this->orTextContains($query, 'requests.active_team_public_id', $subject->identifier);
                $this->orTextContains($query, 'requests.requesting_user_public_id', $subject->identifier);
                $this->orJsonTextContains($query, 'requests.filters', $subject->identifier);
                $this->orJsonTextContains($query, 'credentials.allowed_dataset', $subject->identifier);
                $this->orJsonTextContains($query, 'credentials.allowed_columns', $subject->identifier);
            });
    }

    private function removeArtifactFiles(DataLifecycleSubject $subject, DataLifecycleOperation $operation): int
    {
        $removed = 0;

        foreach ($this->matchingArtifacts($subject)->pluck('artifacts.file_object_public_id')->all() as $filePublicId) {
            if (! is_string($filePublicId) || $filePublicId === '') {
                continue;
            }

            $result = $operation === DataLifecycleOperation::Anonymize
                ? $this->files->anonymize($filePublicId, reason: 'Privacy export artifact lifecycle execution.')
                : $this->files->delete($filePublicId, reason: 'Privacy export artifact lifecycle execution.');

            if ($result->completed) {
                $removed++;
            }
        }

        return $removed;
    }

    private function expireArtifacts(DataLifecycleSubject $subject): int
    {
        return $this->matchingArtifacts($subject)->update([
            'status' => ReportExportStatus::Expired->value,
            'file_object_id' => null,
            'file_object_public_id' => null,
            'filename' => 'privacy-redacted-export-artifact',
            'content_type' => 'application/octet-stream',
            'size_bytes' => 0,
            'checksum_sha256' => null,
            'available_at' => null,
            'failed_at' => null,
            'expires_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function redactRequests(DataLifecycleSubject $subject): int
    {
        return $this->matchingRequests($subject)->update([
            'filters' => $this->redactedPayload(),
            'sorting' => $this->redactedPayload(),
            'visible_columns' => $this->redactedPayload(),
            'column_order' => $this->redactedPayload(),
            'time_range' => null,
            'authorization_snapshot' => $this->redactedPayload(),
            'status' => ReportExportStatus::Expired->value,
            'safe_error_summary' => null,
            'finished_at' => now('UTC'),
            'expires_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function activeStatuses(): array
    {
        return [
            ReportExportStatus::Requested->value,
            ReportExportStatus::Queued->value,
            ReportExportStatus::Generating->value,
        ];
    }

    private function orTextContains(Builder $query, string $column, string $value): void
    {
        match ($column) {
            'artifacts.filename' => $query->orWhereRaw('artifacts.filename LIKE ?', [$this->like($value)]),
            'requests.public_id' => $query->orWhereRaw('requests.public_id LIKE ?', [$this->like($value)]),
            'requests.active_team_public_id' => $query->orWhereRaw('requests.active_team_public_id LIKE ?', [$this->like($value)]),
            'requests.requesting_user_public_id' => $query->orWhereRaw('requests.requesting_user_public_id LIKE ?', [$this->like($value)]),
            'safe_error_summary' => $query->orWhereRaw('safe_error_summary LIKE ?', [$this->like($value)]),
            default => null,
        };
    }

    private function orJsonTextContains(Builder $query, string $column, string $value): void
    {
        match ($column) {
            'authorization_snapshot' => $query->orWhereRaw('authorization_snapshot::text LIKE ?', [$this->like($value)]),
            'column_order' => $query->orWhereRaw('column_order::text LIKE ?', [$this->like($value)]),
            'credentials.allowed_columns' => $query->orWhereRaw('credentials.allowed_columns::text LIKE ?', [$this->like($value)]),
            'credentials.allowed_dataset' => $query->orWhereRaw('credentials.allowed_dataset::text LIKE ?', [$this->like($value)]),
            'filters' => $query->orWhereRaw('filters::text LIKE ?', [$this->like($value)]),
            'requests.authorization_snapshot' => $query->orWhereRaw('requests.authorization_snapshot::text LIKE ?', [$this->like($value)]),
            'requests.filters' => $query->orWhereRaw('requests.filters::text LIKE ?', [$this->like($value)]),
            'sorting' => $query->orWhereRaw('sorting::text LIKE ?', [$this->like($value)]),
            'time_range' => $query->orWhereRaw('time_range::text LIKE ?', [$this->like($value)]),
            'visible_columns' => $query->orWhereRaw('visible_columns::text LIKE ?', [$this->like($value)]),
            default => null,
        };
    }

    private function like(string $value): string
    {
        return '%'.$value.'%';
    }

    private function redactedPayload(): string
    {
        return json_encode(['privacy' => 'redacted'], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function records(Builder $query, array $columns): array
    {
        $records = [];

        foreach ($query->get($columns) as $record) {
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
