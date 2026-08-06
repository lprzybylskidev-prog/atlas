<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Files\Application\DTOs\MalwareScanResult;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Contracts\FileAvailability;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileMaintenance;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Application\Public\DTOs\DownloadableFile;
use App\Modules\Core\Files\Application\Public\DTOs\FileLifecycleResult;
use App\Modules\Core\Files\Application\Public\DTOs\FileMaintenanceResult;
use App\Modules\Core\Files\Application\Public\DTOs\StoredFile;
use App\Modules\Core\Files\Application\Public\Exceptions\FileNotAvailableForDownload;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Files\Presentation\Jobs\ScanFileForMalware;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseFileStorage implements FileAvailability, FileLifecycle, FileMaintenance, FileStorage
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditRecorder $audit,
    ) {}

    public function storeUpload(UploadedFile $file, ?int $actorId = null, ?int $teamId = null, array $metadata = []): StoredFile
    {
        $this->validateUpload($file);

        $disk = Config::string('atlas.files.disk', 'atlas_files');
        $publicId = (string) Str::ulid();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $checksum = hash_file('sha256', $file->getRealPath() ?: $file->path());

        if (! is_string($checksum)) {
            throw new InvalidArgumentException('Uploaded file checksum could not be calculated.');
        }

        $deduplicated = $this->cleanCanonicalByChecksum($checksum, $file->getSize() ?: 0);
        $path = is_object($deduplicated) && is_string($deduplicated->path ?? null)
            ? $deduplicated->path
            : sprintf('%s/%s/%s.%s', now('UTC')->format('Y/m/d'), Str::lower(Str::random(12)), Str::lower((string) Str::ulid()), $extension);
        $storedDisk = is_object($deduplicated) && is_string($deduplicated->disk ?? null) ? $deduplicated->disk : $disk;

        if (! is_object($deduplicated)) {
            Storage::disk($disk)->put($path, file_get_contents($file->getRealPath() ?: $file->path()) ?: '', ['visibility' => 'private']);
        }

        $id = (int) $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->insertGetId([
            'public_id' => $publicId,
            'disk' => $storedDisk,
            'path' => $path,
            'canonical_file_object_id' => is_object($deduplicated) ? $this->intValue($deduplicated->canonical_file_object_id ?? $deduplicated->id ?? null) : null,
            'physical_owner' => ! is_object($deduplicated),
            'original_name' => $file->getClientOriginalName(),
            'extension' => $extension,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'checksum_sha256' => $checksum,
            'scan_state' => is_object($deduplicated) ? FileScanState::Clean->value : FileScanState::Pending->value,
            'scan_state_changed_at' => now(),
            'available_at' => is_object($deduplicated) ? now() : null,
            'quarantined_at' => now(),
            'metadata' => $this->json($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('file.uploaded', 'succeeded', $actorId, $teamId, $publicId, [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $checksum,
            'deduplicated' => is_object($deduplicated),
        ]);

        if (is_object($deduplicated)) {
            $this->recordDeduplicatedEvidence($id, $checksum, $this->intValue($deduplicated->id ?? null));
            $this->recordAudit('file.deduplicated', 'succeeded', $actorId, $teamId, $publicId, [
                'canonical_file_public_id' => $this->string($deduplicated->public_id ?? null),
                'checksum_sha256' => $checksum,
            ]);
        } else {
            $this->dispatchScan($id, $file->getSize() ?: 0);
        }

        return new StoredFile(
            $publicId,
            $file->getClientOriginalName(),
            $file->getMimeType() ?: 'application/octet-stream',
            $file->getSize() ?: 0,
            $checksum,
            is_object($deduplicated) ? FileScanState::Clean : FileScanState::Pending,
            is_object($deduplicated),
            $id,
        );
    }

    public function storeGenerated(string $filename, string $mimeType, string $contents, ?int $actorId = null, ?int $teamId = null, array $metadata = []): StoredFile
    {
        $disk = Config::string('atlas.files.disk', 'atlas_files');
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $checksum = hash('sha256', $contents);
        $sizeBytes = strlen($contents);
        $publicId = (string) Str::ulid();
        $path = sprintf('%s/%s/%s', now('UTC')->format('Y/m/d'), Str::lower(Str::random(12)), Str::lower((string) Str::ulid()).($extension === '' ? '' : '.'.$extension));

        Storage::disk($disk)->put($path, $contents, ['visibility' => 'private']);

        $id = (int) $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->insertGetId([
            'public_id' => $publicId,
            'disk' => $disk,
            'path' => $path,
            'canonical_file_object_id' => null,
            'physical_owner' => true,
            'original_name' => $filename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'scan_state' => FileScanState::Clean->value,
            'scan_state_changed_at' => now(),
            'scan_attempts' => 0,
            'available_at' => now(),
            'quarantined_at' => now(),
            'metadata' => $this->json($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('file.generated', 'succeeded', $actorId, $teamId, $publicId, [
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
        ]);

        return new StoredFile(
            publicId: $publicId,
            originalName: $filename,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            checksumSha256: $checksum,
            scanState: FileScanState::Clean,
            deduplicated: false,
            internalId: $id,
        );
    }

    public function cleanDownloadPath(string $publicId, ?int $actorId = null, ?int $teamId = null): string
    {
        return $this->cleanDownloadFile($publicId, $actorId, $teamId)->path;
    }

    public function clean(string $publicId): bool
    {
        return $this->db->table(FilesDatabaseTable::FILE_OBJECTS)
            ->where('public_id', $publicId)
            ->where('scan_state', FileScanState::Clean->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function cleanDownloadFile(string $publicId, ?int $actorId = null, ?int $teamId = null): DownloadableFile
    {
        $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($row) || ($row->scan_state ?? null) !== FileScanState::Clean->value) {
            $this->recordAudit('file.download_blocked', 'rejected', $actorId, $teamId, $publicId);

            throw FileNotAvailableForDownload::blocked($publicId);
        }

        $disk = is_string($row->disk ?? null) ? $row->disk : Config::string('atlas.files.disk', 'atlas_files');
        $path = is_string($row->path ?? null) ? $row->path : '';

        if (! Storage::disk($disk)->exists($path)) {
            $this->recordAudit('file.download_missing', 'failed', $actorId, $teamId, $publicId);

            throw FileNotAvailableForDownload::blocked($publicId);
        }

        $this->recordAudit('file.downloaded', 'succeeded', $actorId, $teamId, $publicId);

        return new DownloadableFile(
            publicId: $publicId,
            disk: $disk,
            path: $path,
            filename: $this->string($row->original_name ?? null) ?? 'download',
            mimeType: $this->string($row->mime_type ?? null) ?? 'application/octet-stream',
            sizeBytes: $this->intValue($row->size_bytes ?? null),
            checksumSha256: $this->string($row->checksum_sha256 ?? null) ?? '',
        );
    }

    public function markScanning(int $fileObjectId): ?object
    {
        return $this->db->transaction(function () use ($fileObjectId): ?object {
            $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->lockForUpdate()->first();

            if (! is_object($row) || ($row->deleted_at ?? null) !== null) {
                return null;
            }

            if (in_array($row->scan_state, [FileScanState::Clean->value, FileScanState::Infected->value, FileScanState::Unsupported->value], true)) {
                return $row;
            }

            $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->update([
                'scan_state' => FileScanState::Scanning->value,
                'scan_state_changed_at' => now(),
                'scan_attempts' => $this->intValue($row->scan_attempts ?? null) + 1,
                'last_scan_queued_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordAudit('file.scan_started', 'succeeded', null, null, $this->string($row->public_id ?? null));

            return $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->first();
        });
    }

    public function recordScanResult(int $fileObjectId, MalwareScanResult $result): void
    {
        $this->db->transaction(function () use ($fileObjectId, $result): void {
            $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->lockForUpdate()->first();

            if (! is_object($row)) {
                return;
            }

            $currentChecksum = $this->string($row->checksum_sha256 ?? null);
            $state = $currentChecksum === $result->checksumSha256 ? $result->result : FileScanState::Pending;

            $this->db->table(FilesDatabaseTable::FILE_SCAN_EVIDENCE)->insert([
                'file_object_id' => $fileObjectId,
                'provider' => $result->provider,
                'engine_version' => $result->engineVersion,
                'signature_version' => $result->signatureVersion,
                'scanned_at' => $result->scannedAt,
                'result' => $result->result->value,
                'threat_name' => $result->threatName,
                'checksum_sha256' => $result->checksumSha256,
                'metadata' => $this->json($result->metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->update([
                'scan_state' => $state->value,
                'scan_state_changed_at' => now(),
                'available_at' => $state === FileScanState::Clean ? now() : null,
                'updated_at' => now(),
            ]);

            $this->recordAudit('file.scan_completed', $state === FileScanState::Clean ? 'succeeded' : 'blocked', null, null, $this->string($row->public_id ?? null), [
                'provider' => $result->provider,
                'result' => $result->result->value,
                'checksum_sha256' => $result->checksumSha256,
                'threat_name' => $result->threatName,
            ]);
        });
    }

    public function markScanFailed(int $fileObjectId): void
    {
        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->update([
            'scan_state' => FileScanState::Failed->value,
            'scan_state_changed_at' => now(),
            'available_at' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function rescan(string $publicId, ?int $actorId = null, ?int $teamId = null, array $metadata = []): bool
    {
        $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($row)) {
            return false;
        }

        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $row->id)->update([
            'scan_state' => FileScanState::Pending->value,
            'scan_state_changed_at' => now(),
            'available_at' => null,
            'acknowledged_by_user_id' => null,
            'acknowledged_at' => null,
            'acknowledgement_reason' => null,
            'updated_at' => now(),
        ]);

        $this->recordAudit('file.rescan_requested', 'succeeded', $actorId, $teamId, $publicId, $metadata);
        $this->dispatchScan($this->intValue($row->id ?? null), $this->intValue($row->size_bytes ?? null));

        return true;
    }

    public function replace(string $publicId, UploadedFile $replacement, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $existing = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($existing)) {
            return new FileLifecycleResult($publicId, 'replace', false);
        }

        $stored = $this->storeUpload($replacement, $actorId, $teamId, [
            'replacement_for' => $publicId,
            'reason' => $reason,
        ]);

        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $existing->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('file.replaced', 'succeeded', $actorId, $teamId, $publicId, [
            'replacement_file_public_id' => $stored->publicId,
            'reason' => $reason,
        ]);

        return new FileLifecycleResult($publicId, 'replace', true, $stored->publicId);
    }

    public function delete(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($row)) {
            return new FileLifecycleResult($publicId, 'delete', false);
        }

        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $row->id)->update([
            'deleted_at' => now(),
            'available_at' => null,
            'updated_at' => now(),
        ]);

        if ($this->boolValue($row->physical_owner ?? null) && ! $this->hasLivePhysicalReferences($this->string($row->disk ?? null), $this->string($row->path ?? null), $this->intValue($row->id ?? null))) {
            Storage::disk($this->string($row->disk ?? null) ?? Config::string('atlas.files.disk', 'atlas_files'))->delete($this->string($row->path ?? null) ?? '');
        }

        $this->recordAudit('file.deleted', 'succeeded', $actorId, $teamId, $publicId, [
            'reason' => $reason,
            'physical_owner' => $this->boolValue($row->physical_owner ?? null),
        ]);

        return new FileLifecycleResult($publicId, 'delete', true);
    }

    public function anonymize(string $publicId, ?int $actorId = null, ?int $teamId = null, string $reason = ''): FileLifecycleResult
    {
        $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($row)) {
            return new FileLifecycleResult($publicId, 'anonymize', false);
        }

        $metadata = $this->decodeMetadata($row->metadata ?? null);
        $metadata['anonymized'] = true;
        $metadata['anonymized_original_name_hash'] = hash('sha256', $this->string($row->original_name ?? null) ?? '');

        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $row->id)->update([
            'original_name' => 'anonymized-'.$publicId,
            'metadata' => $this->json($metadata),
            'anonymized_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('file.anonymized', 'succeeded', $actorId, $teamId, $publicId, ['reason' => $reason]);

        return new FileLifecycleResult($publicId, 'anonymize', true);
    }

    public function createRetentionCopy(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        return $this->createControlledCopy(
            publicId: $publicId,
            purpose: $purpose,
            operation: 'retention_copy',
            auditAction: 'file.retention_copy_created',
            pathPrefix: 'retention/copies',
            actorId: $actorId,
            teamId: $teamId,
        );
    }

    public function createRetentionExport(string $publicId, string $purpose, ?int $actorId = null, ?int $teamId = null): FileLifecycleResult
    {
        return $this->createControlledCopy(
            publicId: $publicId,
            purpose: $purpose,
            operation: 'retention_export',
            auditAction: 'file.retention_export_created',
            pathPrefix: 'retention/exports',
            actorId: $actorId,
            teamId: $teamId,
        );
    }

    private function createControlledCopy(
        string $publicId,
        string $purpose,
        string $operation,
        string $auditAction,
        string $pathPrefix,
        ?int $actorId,
        ?int $teamId,
    ): FileLifecycleResult {
        $row = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->whereNull('deleted_at')->first();

        if (! is_object($row) || trim($purpose) === '') {
            return new FileLifecycleResult($publicId, $operation, false);
        }

        $copyPublicId = (string) Str::ulid();
        $sourcePath = $this->string($row->path ?? null) ?? '';
        $disk = $this->string($row->disk ?? null) ?? Config::string('atlas.files.disk', 'atlas_files');
        $extension = $this->string($row->extension ?? null) ?? 'bin';
        $copyPath = sprintf('%s/%s/%s.%s', $pathPrefix, now('UTC')->format('Y/m/d'), Str::lower((string) Str::ulid()), $extension);

        if (! Storage::disk($disk)->exists($sourcePath)) {
            return new FileLifecycleResult($publicId, $operation, false);
        }

        Storage::disk($disk)->copy($sourcePath, $copyPath);

        $this->db->table(FilesDatabaseTable::FILE_OBJECTS)->insert([
            'public_id' => $copyPublicId,
            'disk' => $disk,
            'path' => $copyPath,
            'retention_source_file_object_id' => $this->intValue($row->id ?? null),
            'physical_owner' => true,
            'original_name' => $this->string($row->original_name ?? null) ?? $copyPublicId,
            'extension' => $extension,
            'mime_type' => $this->string($row->mime_type ?? null) ?? 'application/octet-stream',
            'size_bytes' => $this->intValue($row->size_bytes ?? null),
            'checksum_sha256' => $this->string($row->checksum_sha256 ?? null) ?? '',
            'scan_state' => $this->string($row->scan_state ?? null) ?? FileScanState::Pending->value,
            'scan_state_changed_at' => now(),
            'available_at' => $this->string($row->scan_state ?? null) === FileScanState::Clean->value ? now() : null,
            'quarantined_at' => now(),
            'retention_purpose' => $purpose,
            'metadata' => $this->json([$operation.'_of' => $publicId, 'purpose' => $purpose]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit($auditAction, 'succeeded', $actorId, $teamId, $publicId, [
            $operation.'_file_public_id' => $copyPublicId,
            'purpose' => $purpose,
        ]);

        return new FileLifecycleResult($publicId, $operation, true, $copyPublicId);
    }

    public function pruneTemporaryFiles(int $ttlMinutes): FileMaintenanceResult
    {
        $prefix = Config::string('atlas.files.temporary_scan_prefix', 'atlas-file-scan-');
        $threshold = now()->subMinutes(max(1, $ttlMinutes))->getTimestamp();
        $deleted = 0;
        $failed = 0;

        foreach (glob(storage_path('app/'.$prefix.'*')) ?: [] as $path) {
            if (! is_file($path) || filemtime($path) === false || filemtime($path) > $threshold) {
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        $this->audit->record(new AuditEvent(
            module: 'files',
            action: 'file.temporary_pruned',
            result: $failed === 0 ? 'succeeded' : 'partial',
            source: 'system',
            metadata: [
                'deleted_temporary_files' => $deleted,
                'failed_temporary_deletes' => $failed,
                'ttl_minutes' => max(1, $ttlMinutes),
            ],
            security: true,
            securityCategory: SecurityAuditCategory::Files,
        ));

        return new FileMaintenanceResult($deleted, $failed);
    }

    private function validateUpload(UploadedFile $file): void
    {
        $size = $file->getSize() ?: 0;

        if ($size <= 0 || $size > Config::integer('atlas.files.max_upload_bytes', 25 * 1024 * 1024)) {
            throw new InvalidArgumentException('Uploaded file size is outside the accepted range.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = Config::array('atlas.files.allowed_extensions');

        if (! in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('Uploaded file extension is not accepted.');
        }

        $mimeType = $file->getMimeType();
        $allowedMimeTypes = Config::array('atlas.files.allowed_mime_types');

        if (! is_string($mimeType) || ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Uploaded file MIME type is not accepted.');
        }
    }

    private function cleanCanonicalByChecksum(string $checksum, int $sizeBytes): ?object
    {
        return $this->db->table(FilesDatabaseTable::FILE_OBJECTS)
            ->where('checksum_sha256', $checksum)
            ->where('size_bytes', $sizeBytes)
            ->where('scan_state', FileScanState::Clean->value)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();
    }

    private function recordDeduplicatedEvidence(int $fileObjectId, string $checksum, int $canonicalId): void
    {
        $latest = $this->db->table(FilesDatabaseTable::FILE_SCAN_EVIDENCE)
            ->where('file_object_id', $canonicalId)
            ->orderByDesc('scanned_at')
            ->first();

        $this->db->table(FilesDatabaseTable::FILE_SCAN_EVIDENCE)->insert([
            'file_object_id' => $fileObjectId,
            'provider' => 'deduplicated',
            'engine_version' => $this->string($latest->engine_version ?? null),
            'signature_version' => $this->string($latest->signature_version ?? null),
            'scanned_at' => now(),
            'result' => FileScanState::Clean->value,
            'threat_name' => null,
            'checksum_sha256' => $checksum,
            'metadata' => $this->json(['canonical_file_object_id' => $canonicalId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dispatchScan(int $fileObjectId, int $sizeBytes): void
    {
        $job = new ScanFileForMalware($fileObjectId);
        $threshold = max(1, Config::integer('atlas.files.large_upload_scan_threshold_bytes', 10 * 1024 * 1024));
        $queue = $sizeBytes >= $threshold
            ? Config::string('atlas.files.large_scan_queue', 'files-large')
            : Config::string('atlas.files.scan_queue', 'files');

        dispatch($job->onQueue($queue));
    }

    private function hasLivePhysicalReferences(?string $disk, ?string $path, int $exceptId): bool
    {
        if ($disk === null || $path === null || $path === '') {
            return false;
        }

        return $this->db->table(FilesDatabaseTable::FILE_OBJECTS)
            ->where('disk', $disk)
            ->where('path', $path)
            ->where('id', '!=', $exceptId)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    private function recordAudit(string $action, string $result, ?int $actorId, ?int $teamId, ?string $filePublicId, array $metadata = []): void
    {
        $actorPublicId = $actorId === null ? null : $this->publicId(IdentityDatabaseTable::USERS, $actorId);
        $teamPublicId = $teamId === null ? null : $this->publicId(TeamsDatabaseTable::TEAMS, $teamId);

        $this->audit->record(new AuditEvent(
            module: 'files',
            action: $action,
            result: $result,
            source: app()->runningInConsole() ? 'system' : 'http',
            actorPublicId: $actorPublicId,
            targetType: 'file',
            targetPublicId: $filePublicId,
            aggregateType: 'file',
            aggregatePublicId: $filePublicId,
            teamPublicId: $teamPublicId,
            metadata: $metadata,
            security: true,
            securityCategory: SecurityAuditCategory::Files,
        ));
    }

    private function publicId(string $table, int $id): ?string
    {
        $value = $this->db->table($table)->where('id', $id)->value('public_id');

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, scalar|null>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function decodeMetadata(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $metadata = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key) && (is_scalar($item) || $item === null)) {
                $metadata[$key] = $item;
            }
        }

        return $metadata;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function boolValue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
