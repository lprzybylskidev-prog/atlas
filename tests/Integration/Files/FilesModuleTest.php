<?php

declare(strict_types=1);

namespace Tests\Integration\Files;

use App\Modules\Core\Files\Application\Contracts\MalwareScanner;
use App\Modules\Core\Files\Application\DTOs\MalwareScanResult;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Exceptions\FileNotAvailableForDownload;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileMaintenance;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Modules\Core\Files\Presentation\Jobs\ScanFileForMalware;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class FilesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_enters_quarantine_and_download_is_blocked_until_clean_scan(): void
    {
        $this->useFakeStorage();
        Queue::fake();

        $stored = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('notice.txt'));

        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'public_id' => $stored->publicId,
            'scan_state' => FileScanState::Pending->value,
        ]);

        $this->expectException(FileNotAvailableForDownload::class);
        $this->app->make(FileStorage::class)->cleanDownloadPath($stored->publicId);
    }

    public function test_clean_scan_records_evidence_and_allows_download_path(): void
    {
        $this->useFakeStorage();
        Queue::fake();

        $stored = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('notice.txt'));
        $this->scanStoredFile($stored->publicId);

        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'public_id' => $stored->publicId,
            'scan_state' => FileScanState::Clean->value,
            'checksum_sha256' => $stored->checksumSha256,
        ]);
        $this->assertDatabaseHas(DatabaseTable::FILE_SCAN_EVIDENCE, [
            'provider' => 'fake',
            'result' => FileScanState::Clean->value,
            'checksum_sha256' => $stored->checksumSha256,
        ]);

        self::assertNotSame('', $this->app->make(FileStorage::class)->cleanDownloadPath($stored->publicId));
    }

    public function test_infected_and_unsupported_scans_remain_blocked(): void
    {
        foreach ([FileScanState::Infected, FileScanState::Unsupported] as $state) {
            $this->useFakeStorage($state->value);
            Queue::fake();

            $stored = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile($state->value.'.txt'));
            $this->scanStoredFile($stored->publicId);

            $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
                'public_id' => $stored->publicId,
                'scan_state' => $state->value,
            ]);

            $blocked = false;

            try {
                $this->app->make(FileStorage::class)->cleanDownloadPath($stored->publicId);
            } catch (FileNotAvailableForDownload) {
                $blocked = true;
            }

            self::assertTrue($blocked, 'Blocked file was downloadable.');
        }
    }

    public function test_scan_retry_exhaustion_keeps_file_failed_and_blocked(): void
    {
        $this->useFakeStorage();
        Queue::fake();
        Config::set('atlas.files.scan_max_attempts', 1);
        $this->app->bind(MalwareScanner::class, fn (): MalwareScanner => new class implements MalwareScanner
        {
            public function scan(string $absolutePath, string $checksumSha256): MalwareScanResult
            {
                throw new RuntimeException('Scanner unavailable.');
            }

            public function available(): bool
            {
                return false;
            }
        });

        $stored = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('notice.txt'));

        try {
            $this->scanStoredFile($stored->publicId);
            self::fail('Scanner failure did not throw.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
                'public_id' => $stored->publicId,
                'scan_state' => FileScanState::Failed->value,
            ]);
        }

        $this->expectException(FileNotAvailableForDownload::class);
        $this->app->make(FileStorage::class)->cleanDownloadPath($stored->publicId);
    }

    public function test_checksum_change_invalidates_previous_scan_result(): void
    {
        $this->useFakeStorage();
        Queue::fake();

        $stored = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('notice.txt'));
        $fileId = $this->fileId($stored->publicId);

        $this->app->make(DatabaseFileStorage::class)->recordScanResult($fileId, new MalwareScanResult(
            provider: 'fake',
            result: FileScanState::Clean,
            checksumSha256: str_repeat('a', 64),
            scannedAt: CarbonImmutable::now('UTC'),
        ));

        $this->assertDatabaseHas(DatabaseTable::FILE_OBJECTS, [
            'public_id' => $stored->publicId,
            'scan_state' => FileScanState::Pending->value,
        ]);
    }

    public function test_clean_duplicate_upload_reuses_canonical_physical_file_and_scan_evidence(): void
    {
        $this->useFakeStorage();
        Queue::fake();

        $first = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('first.txt'));
        $this->scanStoredFile($first->publicId);
        $second = $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('second.txt'));

        self::assertTrue($second->deduplicated);
        self::assertSame(FileScanState::Clean, $second->scanState);

        $firstPath = $this->fileValue($first->publicId, 'path');
        $secondPath = $this->fileValue($second->publicId, 'path');

        self::assertSame($firstPath, $secondPath);
        $this->assertDatabaseHas(DatabaseTable::FILE_SCAN_EVIDENCE, [
            'provider' => 'deduplicated',
            'result' => FileScanState::Clean->value,
            'checksum_sha256' => $first->checksumSha256,
        ]);
    }

    public function test_large_upload_scan_uses_large_files_queue(): void
    {
        $this->useFakeStorage();
        Queue::fake();
        Config::set('atlas.files.large_upload_scan_threshold_bytes', 5);
        Config::set('atlas.files.large_scan_queue', 'files-large-test');

        $this->app->make(FileStorage::class)->storeUpload($this->uploadFile('large.txt'));

        Queue::assertPushed(ScanFileForMalware::class, fn (ScanFileForMalware $job): bool => $job->queue === 'files-large-test');
    }

    public function test_lifecycle_operations_are_audited_and_safe_for_shared_physical_files(): void
    {
        $this->useFakeStorage();
        Queue::fake();

        $storage = $this->app->make(FileStorage::class);
        $lifecycle = $this->app->make(FileLifecycle::class);

        $first = $storage->storeUpload($this->uploadFile('first.txt'));
        $this->scanStoredFile($first->publicId);
        $second = $storage->storeUpload($this->uploadFile('second.txt'));
        $path = $this->fileStringValue($first->publicId, 'path');

        self::assertTrue($lifecycle->anonymize($second->publicId, reason: 'Privacy request')->completed);
        self::assertTrue($lifecycle->createRetentionCopy($first->publicId, 'legal_hold')->completed);
        self::assertTrue($lifecycle->createRetentionExport($first->publicId, 'privacy_export')->completed);
        self::assertTrue($lifecycle->delete($first->publicId, reason: 'Retention expired')->completed);
        self::assertTrue(Storage::disk('atlas_files')->exists($path));
        self::assertTrue($lifecycle->replace($second->publicId, $this->uploadFile('replacement.txt'), reason: 'Corrected file')->completed);

        foreach (['file.anonymized', 'file.retention_copy_created', 'file.retention_export_created', 'file.deleted', 'file.replaced'] as $action) {
            $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
                'module' => 'files',
                'action' => $action,
                'result' => 'succeeded',
            ]);
        }
    }

    public function test_temporary_file_pruning_deletes_expired_scan_files_and_audits_cleanup(): void
    {
        $this->useFakeStorage();
        $prefix = Config::string('atlas.files.temporary_scan_prefix', 'atlas-file-scan-');
        $path = storage_path('app/'.$prefix.'expired');
        file_put_contents($path, 'orphan');
        touch($path, now()->subMinutes(120)->getTimestamp());

        $result = $this->app->make(FileMaintenance::class)->pruneTemporaryFiles(60);

        self::assertSame(1, $result->deletedTemporaryFiles);
        self::assertFileDoesNotExist($path);
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'files',
            'action' => 'file.temporary_pruned',
            'result' => 'succeeded',
        ]);
    }

    private function useFakeStorage(string $scannerResult = 'clean'): void
    {
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');
        Config::set('atlas.files.scanner', 'fake');
        Config::set('atlas.files.fake_scanner_result', $scannerResult);
        Config::set('atlas.files.allowed_extensions', ['txt']);
        Config::set('atlas.files.allowed_mime_types', ['text/plain', 'application/octet-stream']);
        Config::set('atlas.files.scan_queue', 'files-test');
        Config::set('atlas.files.large_scan_queue', 'files-large-test');
        Config::set('atlas.files.large_upload_scan_threshold_bytes', 10 * 1024 * 1024);
        Config::set('queue.default', 'sync');
    }

    private function uploadFile(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'Atlas file test content.');
    }

    private function scanStoredFile(string $publicId): void
    {
        $fileId = $this->fileId($publicId);

        (new ScanFileForMalware($fileId))->handle(
            $this->app->make(DatabaseFileStorage::class),
            $this->app->make(MalwareScanner::class),
            $this->app->make(OperationalModuleGuard::class),
        );
    }

    private function fileValue(string $publicId, string $column): mixed
    {
        return $this->app['db']->table(DatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->value($column);
    }

    private function fileStringValue(string $publicId, string $column): string
    {
        $value = $this->fileValue($publicId, $column);

        if (! is_string($value)) {
            self::fail('Stored file string value was not found.');
        }

        return $value;
    }

    private function fileId(string $publicId): int
    {
        $value = $this->fileValue($publicId, 'id');

        if (! is_numeric($value)) {
            self::fail('Stored file id was not found.');
        }

        return (int) $value;
    }
}
