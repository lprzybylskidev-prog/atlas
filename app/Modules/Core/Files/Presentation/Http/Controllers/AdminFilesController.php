<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminFilesController
{
    public function __construct(
        private DatabaseFileStorage $files,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::FILES);
        $allRows = $this->rows();
        $filters = $this->filters($request, $allRows);
        $filteredRows = $this->filteredRows($allRows, $filters);
        $result = $this->tableResult($request, $definition, $filteredRows);
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Files/Index', [
            'files' => $result->rows,
            'scanEvidence' => $result->rows,
            'summary' => $this->summary($allRows, $result->total),
            'filterOptions' => $this->filterOptions($allRows),
            'table' => $table,
        ]);
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function rows(): array
    {
        return array_values(DB::table(FilesDatabaseTable::FILE_OBJECTS.' as file_objects')
            ->leftJoin(FilesDatabaseTable::FILE_SCAN_EVIDENCE.' as file_scan_evidence', function (JoinClause $join): void {
                $join
                    ->on('file_scan_evidence.file_object_id', '=', 'file_objects.id')
                    ->whereRaw('file_scan_evidence.id = (select max(evidence.id) from '.FilesDatabaseTable::FILE_SCAN_EVIDENCE.' evidence where evidence.file_object_id = file_objects.id)');
            })
            ->leftJoin(IdentityDatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'file_objects.acknowledged_by_user_id')
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
                'file_scan_evidence.result',
                'file_scan_evidence.threat_name',
            ])
            ->map(fn (object $row): array => $this->fileRow($row))
            ->values()
            ->all());
    }

    public function rescan(Request $request, string $file): RedirectResponse
    {
        $actorId = data_get($request->user(), 'id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = is_string($teamPublicId)
            ? DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id')
            : null;

        $requested = $this->files->rescan(
            publicId: $file,
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            teamId: is_numeric($teamId) ? (int) $teamId : null,
            metadata: ['requested_from' => 'admin_files'],
        );

        if (! $requested) {
            return redirect()->route('admin.files.index')->with('flash.messages', [
                FlashMessage::error('flash.files.not_found'),
            ]);
        }

        return redirect()->route('admin.files.index')->with('flash.messages', [
            FlashMessage::success('flash.files.rescan_queued'),
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $validated = $this->validatedAcknowledge($request);
        $files = DB::table(FilesDatabaseTable::FILE_OBJECTS)
            ->whereIn('public_id', $validated['files'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get(['id', 'public_id', 'original_name', 'scan_state', 'acknowledged_at'])
            ->all();

        if (count($files) !== count($validated['files'])) {
            return redirect()->route('admin.files.index')->with('flash.messages', [
                FlashMessage::error('flash.files.not_found'),
            ]);
        }

        $acknowledgeable = array_values(array_filter($files, static fn (object $file): bool => self::canAcknowledgeRow($file)));

        if ($acknowledgeable === []) {
            return redirect()->route('admin.files.index')->with('flash.messages', [
                FlashMessage::error('flash.files.acknowledge_unavailable'),
            ]);
        }

        $this->acknowledgeFiles($request, $acknowledgeable, $validated['reason']);
        $this->recordAcknowledgeAudit($request, $acknowledgeable, $validated['reason']);

        return redirect()->route('admin.files.index')->with('flash.messages', [
            FlashMessage::success(count($acknowledgeable) === 1 ? 'flash.files.acknowledge_single' : 'flash.files.acknowledge_multiple'),
        ]);
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     * @return array{total: int, pending: int, scanning: int, clean: int, infected: int, failed: int, unsupported: int, blocked: int, queued: int, handled: int, visible: int}
     */
    private function summary(array $rows, int $visible): array
    {
        $counts = array_fill_keys(['pending', 'scanning', 'clean', 'infected', 'failed', 'unsupported'], 0);
        $handled = 0;

        foreach ($rows as $row) {
            $state = $this->string($row['scanState'] ?? null);

            if ($state !== null && array_key_exists($state, $counts)) {
                $counts[$state]++;
            }

            if (($row['handlingStatus'] ?? null) === 'handled') {
                $handled++;
            }
        }

        $blocked = count(array_filter(
            $rows,
            static fn (array $row): bool => in_array($row['scanState'] ?? null, ['infected', 'failed', 'unsupported'], true)
                && ($row['handlingStatus'] ?? null) !== 'handled',
        ));

        return [
            'total' => count($rows),
            'pending' => $counts['pending'],
            'scanning' => $counts['scanning'],
            'clean' => $counts['clean'],
            'infected' => $counts['infected'],
            'failed' => $counts['failed'],
            'unsupported' => $counts['unsupported'],
            'blocked' => $blocked,
            'queued' => $counts['pending'] + $counts['scanning'],
            'handled' => $handled,
            'visible' => $visible,
        ];
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function tableResult(Request $request, TableDefinition $definition, array $rows): TableResult
    {
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);

        return $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     * @return array{state: string, extension: string, provider: string, availability: string, handling: string, from: string, to: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'state' => $this->oneOf($request->query('state'), ['all', 'pending', 'scanning', 'clean', 'infected', 'failed', 'unsupported']),
            'extension' => $this->oneOf($request->query('extension'), $this->allOr($this->uniqueValues($rows, 'extension'))),
            'provider' => $this->oneOf($request->query('provider'), $this->allOr($this->uniqueValues($rows, 'provider'))),
            'availability' => $this->oneOf($request->query('availability'), ['all', 'available', 'blocked']),
            'handling' => $this->oneOf($request->query('handling', 'needs_attention'), ['needs_attention', 'handled', 'not_applicable', 'all']),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     * @return array{extensions: list<string>, providers: list<string>}
     */
    private function filterOptions(array $rows): array
    {
        return [
            'extensions' => $this->uniqueValues($rows, 'extension'),
            'providers' => $this->uniqueValues($rows, 'provider'),
        ];
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     * @param  array{state: string, extension: string, provider: string, availability: string, handling: string, from: string, to: string}  $filters
     * @return list<array<string, scalar|null>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['state'] !== 'all' && ($row['scanState'] ?? null) !== $filters['state']) {
                return false;
            }

            foreach (['extension' => 'extension', 'provider' => 'provider'] as $column => $filter) {
                if ($filters[$filter] !== 'all' && ($row[$column] ?? null) !== $filters[$filter]) {
                    return false;
                }
            }

            if ($filters['availability'] === 'available' && ($row['availableAt'] ?? null) === null) {
                return false;
            }

            if ($filters['availability'] === 'blocked' && ($row['availableAt'] ?? null) !== null) {
                return false;
            }

            if ($filters['handling'] === 'needs_attention' && ($row['handlingStatus'] ?? null) !== 'needs_attention') {
                return false;
            }

            if ($filters['handling'] === 'handled' && ($row['handlingStatus'] ?? null) !== 'handled') {
                return false;
            }

            if ($filters['handling'] === 'not_applicable' && ($row['handlingStatus'] ?? null) !== 'not_applicable') {
                return false;
            }

            return self::dateRangeMatches(self::stringField($row, 'createdAt'), $filters['from'], $filters['to']);
        }));
    }

    /**
     * @return array<string, scalar|null>
     */
    private function fileRow(object $row): array
    {
        return [
            'publicId' => $this->string($row->public_id ?? null),
            'originalName' => $this->string($row->original_name ?? null),
            'extension' => $this->string($row->extension ?? null),
            'mimeType' => $this->string($row->mime_type ?? null),
            'sizeBytes' => is_numeric($row->size_bytes ?? null) ? (int) $row->size_bytes : 0,
            'checksumSha256' => $this->string($row->checksum_sha256 ?? null),
            'scanState' => $this->string($row->scan_state ?? null),
            'scanAttempts' => is_numeric($row->scan_attempts ?? null) ? (int) $row->scan_attempts : 0,
            'quarantinedAt' => $this->string($row->quarantined_at ?? null),
            'availableAt' => $this->string($row->available_at ?? null),
            'handlingStatus' => $this->handlingStatus($row),
            'canAcknowledge' => self::canAcknowledgeRow($row),
            'acknowledgedAt' => $this->string($row->acknowledged_at ?? null),
            'acknowledgedBy' => $this->string($row->acknowledged_by ?? null),
            'acknowledgementReason' => $this->string($row->acknowledgement_reason ?? null),
            'createdAt' => $this->string($row->created_at ?? null),
            'provider' => $this->string($row->provider ?? null),
            'engineVersion' => $this->string($row->engine_version ?? null),
            'signatureVersion' => $this->string($row->signature_version ?? null),
            'scannedAt' => $this->string($row->scanned_at ?? null),
            'result' => $this->string($row->result ?? null),
            'threatName' => $this->string($row->threat_name ?? null),
        ];
    }

    /**
     * @return array{files: list<string>, reason: ?string}
     */
    private function validatedAcknowledge(Request $request): array
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*' => ['required', 'string', 'size:26'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $files = $request->input('files', []);
        $reason = $request->input('reason');

        return [
            'files' => array_values(array_unique(array_filter(
                array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', is_array($files) ? $files : []),
                static fn (string $value): bool => $value !== '',
            ))),
            'reason' => is_string($reason) && trim($reason) !== ''
                ? trim($reason)
                : null,
        ];
    }

    /**
     * @param  array<int, object>  $files
     */
    private function acknowledgeFiles(Request $request, array $files, ?string $reason): void
    {
        $actorId = data_get($request->user(), 'id');
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        $now = Carbon::now();

        $ids = array_values(array_filter(
            array_map(static fn (object $file): ?int => self::fileId($file), $files),
            static fn (?int $id): bool => $id !== null,
        ));

        if ($ids === []) {
            return;
        }

        DB::table(FilesDatabaseTable::FILE_OBJECTS)
            ->whereIn('id', $ids)
            ->update([
                'acknowledged_by_user_id' => $actorId,
                'acknowledged_at' => $now,
                'acknowledgement_reason' => $reason,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  array<int, object>  $files
     */
    private function recordAcknowledgeAudit(Request $request, array $files, ?string $reason): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        foreach ($files as $file) {
            $publicId = $this->string($file->public_id ?? null);

            if ($publicId === null) {
                continue;
            }

            $this->audit->record(new AuditEvent(
                module: 'files',
                action: 'file.scan_acknowledge',
                result: 'succeeded',
                source: 'admin',
                actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
                actualActorPublicId: null,
                impersonatedUserPublicId: null,
                targetType: 'file',
                targetPublicId: $publicId,
                aggregateType: 'file',
                aggregatePublicId: $publicId,
                teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
                correlationId: null,
                reason: $reason,
                metadata: [
                    'original_name' => $this->string($file->original_name ?? null),
                    'scan_state' => $this->string($file->scan_state ?? null),
                ],
                security: true,
                securityCategory: SecurityAuditCategory::Files,
            ));
        }
    }

    private function handlingStatus(object $row): string
    {
        if ($this->string($row->acknowledged_at ?? null) !== null) {
            return 'handled';
        }

        return self::canAcknowledgeRow($row) ? 'needs_attention' : 'not_applicable';
    }

    private static function canAcknowledgeRow(object $row): bool
    {
        $state = is_scalar($row->scan_state ?? null) ? (string) $row->scan_state : null;
        $acknowledgedAt = $row->acknowledged_at ?? null;

        return $state !== null
            && $state !== FileScanState::Clean->value
            && ($acknowledgedAt === null || $acknowledgedAt === '');
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function fileId(object $file): ?int
    {
        $values = get_object_vars($file);
        $id = $values['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $this->string($row[$key] ?? null);

            if ($value !== null && $value !== '') {
                $values[$value] = $value;
            }
        }

        ksort($values);

        return array_values($values);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return ['all', ...$values];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        $candidate = $this->string($value) ?? 'all';

        return in_array($candidate, $allowed, true) ? $candidate : 'all';
    }

    private function dateFilter(mixed $value): string
    {
        $date = $this->string($value);

        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    }

    /**
     * @param  array<string, scalar|null>  $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    private static function dateRangeMatches(string $timestamp, string $from, string $to): bool
    {
        if ($from !== '' && substr($timestamp, 0, 10) < $from) {
            return false;
        }

        if ($to !== '' && substr($timestamp, 0, 10) > $to) {
            return false;
        }

        return true;
    }
}
