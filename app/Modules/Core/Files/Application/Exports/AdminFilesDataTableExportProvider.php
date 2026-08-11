<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Exports;

use App\Modules\Core\Files\Infrastructure\Persistence\TableNames\FilesDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class AdminFilesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private UserLookup $users) {}

    public function tableKey(): string
    {
        return RegisteredTables::FILES;
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
        return ExportPermissions::REQUEST;
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
        $records = DB::table(FilesDatabaseTable::FILE_OBJECTS.' as file_objects')
            ->leftJoin(FilesDatabaseTable::FILE_SCAN_EVIDENCE.' as file_scan_evidence', function (JoinClause $join): void {
                $join
                    ->on('file_scan_evidence.file_object_id', '=', 'file_objects.id')
                    ->whereRaw('file_scan_evidence.id = (select max(evidence.id) from '.FilesDatabaseTable::FILE_SCAN_EVIDENCE.' evidence where evidence.file_object_id = file_objects.id)');
            })
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
                'file_objects.acknowledged_by_user_id',
                'file_objects.acknowledgement_reason',
                'file_objects.created_at',
                'file_scan_evidence.provider',
                'file_scan_evidence.engine_version',
                'file_scan_evidence.signature_version',
                'file_scan_evidence.scanned_at',
                'file_scan_evidence.threat_name',
            ]);
        $rows = array_values(collect($this->enrichRowsWithAcknowledgedBy($records->all()))
            ->map(fn (object $row): array => $this->fileRow($row))
            ->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return list<stdClass>
     */
    private function enrichRowsWithAcknowledgedBy(array $rows): array
    {
        $rows = array_values($rows);
        $userIds = [];

        foreach ($rows as $row) {
            $userId = self::nullableInt($row->acknowledged_by_user_id ?? null);

            if ($userId !== null) {
                $userIds[] = $userId;
            }
        }

        $summaries = $this->users->displaySummariesForInternalIds(array_values(array_unique($userIds)));

        foreach ($rows as $row) {
            $userId = self::nullableInt($row->acknowledged_by_user_id ?? null);
            $summary = $userId === null ? null : ($summaries[$userId] ?? null);
            $row->acknowledged_by = $summary?->name;
        }

        return $rows;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
