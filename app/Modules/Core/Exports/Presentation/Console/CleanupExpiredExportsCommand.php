<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Presentation\Console;

use App\Modules\Core\Exports\Application\Public\Contracts\ReportExportMaintenance;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'exports:cleanup-expired';

    protected $description = 'Expire old export artifacts and delete linked private files.';

    public function handle(ReportExportMaintenance $maintenance): int
    {
        $result = $maintenance->cleanupExpired(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        $this->info(sprintf(
            'Expired %d request(s), expired %d artifact(s), deleted %d file(s), failed %d file delete(s).',
            $result->expiredRequests,
            $result->expiredArtifacts,
            $result->deletedFiles,
            $result->failedFileDeletes,
        ));

        return self::SUCCESS;
    }
}
