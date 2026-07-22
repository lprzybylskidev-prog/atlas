<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Presentation\Providers;

use App\Modules\Core\Files\Application\Contracts\MalwareScanner;
use App\Modules\Core\Files\Application\Exports\AdminFilesDataTableExportProvider;
use App\Modules\Core\Files\Application\Permissions\FilesPermissionCatalog;
use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileMaintenance;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Modules\Core\Files\Infrastructure\Scanning\ClamAvMalwareScanner;
use App\Modules\Core\Files\Infrastructure\Scanning\FakeMalwareScanner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class FilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileStorage::class, DatabaseFileStorage::class);
        $this->app->bind(FileLifecycle::class, DatabaseFileStorage::class);
        $this->app->bind(FileMaintenance::class, DatabaseFileStorage::class);
        $this->app->bind(MalwareScanner::class, function (): MalwareScanner {
            $scanner = Config::string('atlas.files.scanner', 'fake');

            if ($scanner === 'clamav') {
                return new ClamAvMalwareScanner(
                    host: Config::string('atlas.files.clamav.host', 'clamav'),
                    port: Config::integer('atlas.files.clamav.port', 3310),
                    timeoutSeconds: Config::integer('atlas.files.clamav.timeout_seconds', 30),
                );
            }

            if ($scanner === 'fake') {
                if ($this->app->environment('production')) {
                    throw new RuntimeException('The Files fake malware scanner is forbidden in production.');
                }

                return new FakeMalwareScanner(Config::string('atlas.files.fake_scanner_result', 'clean'));
            }

            throw new RuntimeException(sprintf('Unsupported Files malware scanner [%s].', $scanner));
        });
        $this->app->tag([FilesPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([AdminFilesDataTableExportProvider::class], 'atlas.admin_data_table_export_providers');
    }
}
