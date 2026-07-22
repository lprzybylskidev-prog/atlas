<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Infrastructure\Persistence;

use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Enums\ReportExportStatus;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportRequestRecord;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseReportExportRequestStore implements ReportExportRequestStore
{
    public function createFromSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord
    {
        $requestFingerprint = $snapshot->requestFingerprint();
        $authorizationFingerprint = $snapshot->authorization->hash();
        $now = now('UTC');

        $existing = DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where('request_fingerprint', $requestFingerprint)
            ->first();

        if ($existing !== null) {
            return $this->recordFromRow($existing);
        }

        $publicId = (string) Str::ulid();

        DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)->insert([
            'public_id' => $publicId,
            'report_key' => $snapshot->reportKey,
            'report_name' => $snapshot->reportName,
            'module_key' => $snapshot->moduleKey,
            'format' => $snapshot->format->value,
            'team_id' => $snapshot->activeTeamId,
            'active_team_public_id' => $snapshot->activeTeamPublicId,
            'requested_by_user_id' => $snapshot->requestingUserId,
            'requesting_user_public_id' => $snapshot->requestingUserPublicId,
            'filters' => json_encode($snapshot->filters, JSON_THROW_ON_ERROR),
            'sorting' => json_encode($snapshot->sorting, JSON_THROW_ON_ERROR),
            'visible_columns' => json_encode($snapshot->visibleColumns, JSON_THROW_ON_ERROR),
            'column_order' => json_encode($snapshot->columnOrder, JSON_THROW_ON_ERROR),
            'time_range' => $snapshot->timeRange === null ? null : json_encode($snapshot->timeRange, JSON_THROW_ON_ERROR),
            'authorization_snapshot' => json_encode($snapshot->authorization->toArray(), JSON_THROW_ON_ERROR),
            'authorization_fingerprint' => $authorizationFingerprint,
            'request_fingerprint' => $requestFingerprint,
            'release_version' => $snapshot->releaseVersion,
            'rule_version' => $snapshot->ruleVersion,
            'status' => ReportExportStatus::Requested->value,
            'synchronous_allowed' => $snapshot->synchronousAllowed,
            'expires_at' => $snapshot->expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $publicId)->first();

        if ($row === null) {
            throw new \RuntimeException('Report export request snapshot was not persisted.');
        }

        return $this->recordFromRow($row);
    }

    public function linkProcessRun(string $requestPublicId, string $processRunPublicId): void
    {
        $processRunId = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->where('public_id', $processRunPublicId)
            ->value('id');

        if (! is_numeric($processRunId)) {
            throw new \RuntimeException('Report export generation process run was not found.');
        }

        DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where('public_id', $requestPublicId)
            ->whereIn('status', [ReportExportStatus::Requested->value, ReportExportStatus::Queued->value])
            ->update([
                'process_run_id' => (int) $processRunId,
                'status' => ReportExportStatus::Queued->value,
                'queued_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    public function markGenerating(string $requestPublicId): void
    {
        DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where('public_id', $requestPublicId)
            ->whereIn('status', [ReportExportStatus::Requested->value, ReportExportStatus::Queued->value, ReportExportStatus::Generating->value])
            ->update([
                'status' => ReportExportStatus::Generating->value,
                'started_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    public function markFailed(string $requestPublicId, string $safeErrorSummary): void
    {
        DB::transaction(function () use ($requestPublicId, $safeErrorSummary): void {
            $requestId = DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)
                ->where('public_id', $requestPublicId)
                ->value('id');

            if (! is_numeric($requestId)) {
                throw new \RuntimeException('Report export request was not found.');
            }

            DB::table(DatabaseTable::REPORT_EXPORT_ARTIFACTS)
                ->where('export_request_id', (int) $requestId)
                ->where('status', ReportExportStatus::Generating->value)
                ->update([
                    'status' => ReportExportStatus::Failed->value,
                    'failed_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);

            DB::table(DatabaseTable::REPORT_EXPORT_REQUESTS)
                ->where('public_id', $requestPublicId)
                ->update([
                    'status' => ReportExportStatus::Failed->value,
                    'failed_at' => now('UTC'),
                    'safe_error_summary' => mb_substr($safeErrorSummary, 0, 2000),
                    'updated_at' => now('UTC'),
                ]);
        });
    }

    private function recordFromRow(object $row): ReportExportRequestRecord
    {
        $fields = get_object_vars($row);

        return new ReportExportRequestRecord(
            publicId: $this->stringField($fields, 'public_id'),
            reportKey: $this->stringField($fields, 'report_key'),
            moduleKey: $this->stringField($fields, 'module_key'),
            format: $this->stringField($fields, 'format'),
            status: $this->stringField($fields, 'status'),
            requestFingerprint: $this->stringField($fields, 'request_fingerprint'),
            authorizationFingerprint: $this->stringField($fields, 'authorization_fingerprint'),
        );
    }

    /**
     * @param  array<mixed>  $fields
     */
    private function stringField(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \UnexpectedValueException(sprintf('Report export request field [%s] must be a non-empty string.', $key));
        }

        return $value;
    }
}
