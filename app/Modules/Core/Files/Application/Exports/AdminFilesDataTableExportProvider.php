<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class AdminFilesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::FILES;
    }

    public function tableName(): string
    {
        return 'Files';
    }

    public function owningModuleKey(): string
    {
        return 'files';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-files-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'originalName' => 'File',
            'extension' => 'Extension',
            'mimeType' => 'MIME type',
            'scanState' => 'State',
            'handlingStatus' => 'Handling',
            'sizeBytes' => 'Size bytes',
            'checksumSha256' => 'SHA-256 checksum',
            'scannedAt' => 'Scanned',
            'provider' => 'Scanner',
            'engineVersion' => 'Engine',
            'signatureVersion' => 'Signatures',
            'scanAttempts' => 'Attempts',
            'quarantinedAt' => 'Quarantined',
            'availableAt' => 'Available',
            'acknowledgedAt' => 'Handled at',
            'acknowledgedBy' => 'Handled by',
            'acknowledgementReason' => 'Handling reason',
            'threatName' => 'Threat',
            'createdAt' => 'Created at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(DB::table(DatabaseTable::FILE_OBJECTS.' as file_objects')
            ->leftJoin(DatabaseTable::FILE_SCAN_EVIDENCE.' as file_scan_evidence', function (JoinClause $join): void {
                $join
                    ->on('file_scan_evidence.file_object_id', '=', 'file_objects.id')
                    ->whereRaw('file_scan_evidence.id = (select max(evidence.id) from '.DatabaseTable::FILE_SCAN_EVIDENCE.' evidence where evidence.file_object_id = file_objects.id)');
            })
            ->leftJoin(DatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'file_objects.acknowledged_by_user_id')
            ->whereNull('file_objects.deleted_at')
            ->orderByDesc('file_objects.created_at')
            ->get([
                'file_objects.public_id',
                'file_objects.original_name',
                'file_objects.extension',
                'file_objects.mime_type',
                'file_objects.size_bytes',
                'file_objects.checksum_sha256',
                'file_objects.scan_state',
                'file_objects.scan_attempts',
                'file_objects.quarantined_at',
                'file_objects.available_at',
                'file_objects.acknowledged_at',
                'file_objects.acknowledgement_reason',
                'file_objects.created_at',
                'acknowledged_users.name as acknowledged_by',
                'file_scan_evidence.provider',
                'file_scan_evidence.engine_version',
                'file_scan_evidence.signature_version',
                'file_scan_evidence.scanned_at',
                'file_scan_evidence.threat_name',
            ])
            ->map(fn (object $row): array => $this->fileRow($row))
            ->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $state = self::filterValue($request, 'state');
            $extension = self::filterValue($request, 'extension');
            $provider = self::filterValue($request, 'provider');
            $availability = self::filterValue($request, 'availability');
            $handling = self::filterValue($request, 'handling');

            if ($state !== 'all' && $row['scanState'] !== $state) {
                return false;
            }

            if ($extension !== 'all' && $row['extension'] !== $extension) {
                return false;
            }

            if ($provider !== 'all' && $row['provider'] !== $provider) {
                return false;
            }

            if ($availability === 'available' && $row['availableAt'] === null) {
                return false;
            }

            if ($availability === 'blocked' && $row['availableAt'] !== null) {
                return false;
            }

            if ($handling === 'needs_attention' && $row['handlingStatus'] !== 'needs_attention') {
                return false;
            }

            if ($handling === 'handled' && $row['handlingStatus'] !== 'handled') {
                return false;
            }

            if ($handling === 'not_applicable' && $row['handlingStatus'] !== 'not_applicable') {
                return false;
            }

            return self::dateRangeMatches(self::stringValue($row['createdAt'] ?? null), self::filterValue($request, 'from'), self::filterValue($request, 'to'));
        }));
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    private function fileRow(object $row): array
    {
        return [
            'publicId' => self::stringValue($row->public_id ?? null),
            'originalName' => self::stringValue($row->original_name ?? null),
            'extension' => self::stringValue($row->extension ?? null),
            'mimeType' => self::stringValue($row->mime_type ?? null),
            'sizeBytes' => is_numeric($row->size_bytes ?? null) ? (int) $row->size_bytes : 0,
            'checksumSha256' => self::stringValue($row->checksum_sha256 ?? null),
            'scanState' => self::stringValue($row->scan_state ?? null),
            'handlingStatus' => self::fileHandlingStatus($row),
            'scanAttempts' => is_numeric($row->scan_attempts ?? null) ? (int) $row->scan_attempts : 0,
            'quarantinedAt' => self::stringValue($row->quarantined_at ?? null),
            'availableAt' => self::stringValue($row->available_at ?? null),
            'acknowledgedAt' => self::stringValue($row->acknowledged_at ?? null),
            'acknowledgedBy' => self::stringValue($row->acknowledged_by ?? null),
            'acknowledgementReason' => self::stringValue($row->acknowledgement_reason ?? null),
            'createdAt' => self::stringValue($row->created_at ?? null),
            'provider' => self::stringValue($row->provider ?? null),
            'engineVersion' => self::stringValue($row->engine_version ?? null),
            'signatureVersion' => self::stringValue($row->signature_version ?? null),
            'scannedAt' => self::stringValue($row->scanned_at ?? null),
            'threatName' => self::stringValue($row->threat_name ?? null),
        ];
    }

    private static function fileHandlingStatus(object $row): string
    {
        if (self::stringValue($row->acknowledged_at ?? null) !== '') {
            return 'handled';
        }

        return self::stringValue($row->scan_state ?? null) === 'clean' ? 'not_applicable' : 'needs_attention';
    }
}
