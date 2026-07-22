<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Optional\Reports\Application\Enums\ReportExportStatus;
use App\Modules\Optional\Reports\Application\Exceptions\ReportArtifactNotDownloadable;
use App\Modules\Optional\Reports\Application\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportArtifactAccess;
use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportMaintenance;
use App\Modules\Optional\Reports\Application\Public\DTOs\DownloadableReportArtifact;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportCleanupResult;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class ReportExportArtifactService implements ReportExportArtifactAccess, ReportExportMaintenance
{
    public function __construct(
        private ConnectionInterface $database,
        private FileStorage $files,
        private FileLifecycle $fileLifecycle,
        private OperationalModuleGuard $modules,
    ) {}

    public function download(string $artifactPublicId, string $actorPublicId, ?string $activeTeamPublicId): DownloadableReportArtifact
    {
        $row = $this->artifactWithRequest($artifactPublicId);
        $requestPublicId = $this->requiredString($row, 'request_public_id');
        $filePublicId = $this->requiredString($row, 'file_object_public_id');
        $teamPublicId = $this->nullableString($row, 'active_team_public_id');
        $requestingUserPublicId = $this->requiredString($row, 'requesting_user_public_id');
        $moduleKey = $this->requiredString($row, 'module_key');

        if ($teamPublicId !== $activeTeamPublicId || $requestingUserPublicId !== $actorPublicId) {
            throw ReportArtifactNotDownloadable::blocked($artifactPublicId);
        }

        if ($this->requiredString($row, 'artifact_status') !== ReportExportStatus::Available->value || $this->requiredString($row, 'request_status') !== ReportExportStatus::Available->value) {
            throw ReportArtifactNotDownloadable::blocked($artifactPublicId);
        }

        if ($this->expired($row->artifact_expires_at ?? null) || $this->expired($row->request_expires_at ?? null)) {
            throw ReportArtifactNotDownloadable::blocked($artifactPublicId);
        }

        $this->modules->ensureAllowed('reports', $teamPublicId, $actorPublicId, ReportsPermissionCatalog::DOWNLOAD);
        $this->modules->ensureAllowed($moduleKey, $teamPublicId, $actorPublicId);
        if ((bool) ($row->audit_export ?? false)) {
            $this->modules->ensureAllowed('reports', $teamPublicId, $actorPublicId, ReportsPermissionCatalog::AUDIT_EXPORT);
        }

        $file = $this->files->cleanDownloadFile(
            publicId: $filePublicId,
            actorId: $this->userId($actorPublicId),
            teamId: $this->teamId($teamPublicId),
        );

        return new DownloadableReportArtifact(
            artifactPublicId: $artifactPublicId,
            exportRequestPublicId: $requestPublicId,
            filePublicId: $filePublicId,
            disk: $file->disk,
            path: $file->path,
            filename: $this->requiredString($row, 'filename'),
            contentType: $this->requiredString($row, 'content_type'),
            sizeBytes: $this->positiveInt($row->size_bytes ?? null),
            checksumSha256: $this->requiredString($row, 'checksum_sha256'),
        );
    }

    public function cleanupExpired(DateTimeImmutable $now): ReportExportCleanupResult
    {
        $expiredArtifacts = $this->database->table(DatabaseTable::REPORT_EXPORT_ARTIFACTS)
            ->where('expires_at', '<=', $now)
            ->where('status', '!=', ReportExportStatus::Expired->value)
            ->get(['id', 'file_object_public_id', 'created_by_user_id'])
            ->all();

        $deletedFiles = 0;
        $failedFileDeletes = 0;

        foreach ($expiredArtifacts as $artifact) {
            $filePublicId = $this->nullableString($artifact, 'file_object_public_id');

            if ($filePublicId === null) {
                continue;
            }

            $result = $this->fileLifecycle->delete(
                publicId: $filePublicId,
                actorId: $this->nullableInt($artifact->created_by_user_id ?? null),
                teamId: null,
                reason: 'Report artifact expired',
            );

            if ($result->completed) {
                $deletedFiles++;
            } else {
                $failedFileDeletes++;
            }
        }

        $expiredArtifactCount = $this->database->table(DatabaseTable::REPORT_EXPORT_ARTIFACTS)
            ->where('expires_at', '<=', $now)
            ->where('status', '!=', ReportExportStatus::Expired->value)
            ->update([
                'status' => ReportExportStatus::Expired->value,
                'updated_at' => now('UTC'),
            ]);

        $expiredRequestCount = $this->database->table(DatabaseTable::REPORT_EXPORT_REQUESTS)
            ->where('expires_at', '<=', $now)
            ->whereNotIn('status', [ReportExportStatus::Expired->value, ReportExportStatus::Cancelled->value])
            ->update([
                'status' => ReportExportStatus::Expired->value,
                'updated_at' => now('UTC'),
            ]);

        return new ReportExportCleanupResult(
            expiredRequests: $expiredRequestCount,
            expiredArtifacts: $expiredArtifactCount,
            deletedFiles: $deletedFiles,
            failedFileDeletes: $failedFileDeletes,
        );
    }

    private function artifactWithRequest(string $artifactPublicId): stdClass
    {
        $row = $this->database->table(DatabaseTable::REPORT_EXPORT_ARTIFACTS.' as artifacts')
            ->join(DatabaseTable::REPORT_EXPORT_REQUESTS.' as requests', 'artifacts.export_request_id', '=', 'requests.id')
            ->where('artifacts.public_id', $artifactPublicId)
            ->first([
                'artifacts.public_id as artifact_public_id',
                'artifacts.status as artifact_status',
                'artifacts.file_object_public_id',
                'artifacts.filename',
                'artifacts.content_type',
                'artifacts.size_bytes',
                'artifacts.checksum_sha256',
                'artifacts.expires_at as artifact_expires_at',
                'requests.public_id as request_public_id',
                'requests.status as request_status',
                'requests.module_key',
                'requests.audit_export',
                'requests.active_team_public_id',
                'requests.requesting_user_public_id',
                'requests.expires_at as request_expires_at',
            ]);

        if ($row instanceof stdClass) {
            return $row;
        }

        throw ReportArtifactNotDownloadable::blocked($artifactPublicId);
    }

    private function userId(string $publicId): ?int
    {
        return $this->idForPublicId(DatabaseTable::USERS, $publicId);
    }

    private function teamId(?string $publicId): ?int
    {
        return $publicId === null ? null : $this->idForPublicId(DatabaseTable::TEAMS, $publicId);
    }

    private function idForPublicId(string $table, string $publicId): ?int
    {
        $id = $this->database->table($table)->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function expired(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return $value <= now('UTC');
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value) <= new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        return true;
    }

    private function requiredString(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw ReportArtifactNotDownloadable::blocked($this->nullableString($row, 'artifact_public_id') ?? 'unknown');
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function positiveInt(mixed $value): int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw ReportArtifactNotDownloadable::blocked('unknown');
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
