<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Presentation\Http\Controllers;

use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminFilesController
{
    public function __construct(private DatabaseFileStorage $files) {}

    public function index(): Response
    {
        $records = DB::table(DatabaseTable::FILE_OBJECTS.' as file_objects')
            ->leftJoin(DatabaseTable::FILE_SCAN_EVIDENCE.' as file_scan_evidence', function (JoinClause $join): void {
                $join
                    ->on('file_scan_evidence.file_object_id', '=', 'file_objects.id')
                    ->whereRaw('file_scan_evidence.id = (select max(evidence.id) from '.DatabaseTable::FILE_SCAN_EVIDENCE.' evidence where evidence.file_object_id = file_objects.id)');
            })
            ->orderByDesc('file_objects.created_at')
            ->limit(200)
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
                'file_objects.created_at',
                'file_scan_evidence.provider',
                'file_scan_evidence.engine_version',
                'file_scan_evidence.signature_version',
                'file_scan_evidence.scanned_at',
                'file_scan_evidence.result',
                'file_scan_evidence.threat_name',
            ])
            ->map(fn (object $row): array => $this->fileRow($row))
            ->values()
            ->all();

        return Inertia::render('Admin/Files/Index', [
            'files' => $records,
            'summary' => $this->summary(),
        ]);
    }

    public function rescan(Request $request, string $file): RedirectResponse
    {
        $actorId = data_get($request->user(), 'id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = is_string($teamPublicId)
            ? DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id')
            : null;

        $requested = $this->files->rescan(
            publicId: $file,
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            teamId: is_numeric($teamId) ? (int) $teamId : null,
            metadata: ['requested_from' => 'admin_files'],
        );

        if (! $requested) {
            return redirect()->route('admin.files.index')->with('error', 'File was not found.');
        }

        return redirect()->route('admin.files.index')->with('success', 'File rescan was queued.');
    }

    /**
     * @return array{total: int, pending: int, scanning: int, clean: int, infected: int, failed: int, unsupported: int, visible: int}
     */
    private function summary(): array
    {
        $rows = DB::table(DatabaseTable::FILE_OBJECTS)
            ->selectRaw('scan_state, count(*) as total')
            ->whereNull('deleted_at')
            ->groupBy('scan_state')
            ->pluck('total', 'scan_state');

        $total = $this->intValue($rows->sum());

        return [
            'total' => $total,
            'pending' => $this->intValue($rows['pending'] ?? null),
            'scanning' => $this->intValue($rows['scanning'] ?? null),
            'clean' => $this->intValue($rows['clean'] ?? null),
            'infected' => $this->intValue($rows['infected'] ?? null),
            'failed' => $this->intValue($rows['failed'] ?? null),
            'unsupported' => $this->intValue($rows['unsupported'] ?? null),
            'visible' => min($total, 200),
        ];
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
            'createdAt' => $this->string($row->created_at ?? null),
            'provider' => $this->string($row->provider ?? null),
            'engineVersion' => $this->string($row->engine_version ?? null),
            'signatureVersion' => $this->string($row->signature_version ?? null),
            'scannedAt' => $this->string($row->scanned_at ?? null),
            'result' => $this->string($row->result ?? null),
            'threatName' => $this->string($row->threat_name ?? null),
        ];
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
