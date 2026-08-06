<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Infrastructure\Scanning;

use App\Modules\Core\Files\Application\Contracts\MalwareScanner;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Contracts\FileScanner;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final readonly class DatabaseFileScanner implements FileScanner
{
    public function __construct(
        private ConnectionInterface $db,
        private DatabaseFileStorage $files,
        private MalwareScanner $scanner,
        private OperationalModuleGuard $modules,
    ) {}

    public function scanNow(string $publicId): FileScanState
    {
        $this->modules->ensureAllowed('files');

        $fileObjectId = $this->fileObjectId($publicId);
        $row = $this->files->markScanning($fileObjectId);

        if (! is_object($row)) {
            return FileScanState::Failed;
        }

        $currentState = $this->scanState($row->scan_state ?? null);

        if (in_array($currentState, [FileScanState::Clean, FileScanState::Infected, FileScanState::Unsupported], true)) {
            return $currentState;
        }

        $temporaryPath = $this->temporaryScanCopy($this->string($row->disk ?? null), $this->string($row->path ?? null));

        try {
            $result = $this->scanner->scan($temporaryPath, $this->string($row->checksum_sha256 ?? null));
            $this->files->recordScanResult($fileObjectId, $result);

            return $this->persistedScanState($fileObjectId);
        } catch (Throwable $exception) {
            $this->files->markScanFailed($fileObjectId);

            throw $exception;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function fileObjectId(string $publicId): int
    {
        $id = $this->db->table(FilesDatabaseTable::FILE_OBJECTS)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->value('id');

        if (! is_numeric($id)) {
            throw new RuntimeException('The file selected for malware scanning could not be found.');
        }

        return (int) $id;
    }

    private function temporaryScanCopy(string $disk, string $path): string
    {
        if ($disk === '' || $path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('The quarantined file is missing from private storage.');
        }

        $temporaryPath = tempnam(storage_path('app'), Config::string('atlas.files.temporary_scan_prefix', 'atlas-file-scan-'));

        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary malware scan file could not be created.');
        }

        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The quarantined file could not be opened for scanning.');
        }

        try {
            file_put_contents($temporaryPath, stream_get_contents($stream));
        } finally {
            fclose($stream);
        }

        return $temporaryPath;
    }

    private function scanState(mixed $value): FileScanState
    {
        return is_string($value) ? FileScanState::tryFrom($value) ?? FileScanState::Failed : FileScanState::Failed;
    }

    private function persistedScanState(int $fileObjectId): FileScanState
    {
        return $this->scanState($this->db->table(FilesDatabaseTable::FILE_OBJECTS)->where('id', $fileObjectId)->value('scan_state'));
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
