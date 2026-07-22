<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Presentation\Console;

use App\Modules\Optional\Reports\Application\Public\Contracts\ReportExportMaintenance;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class CleanupExpiredReportExportsCommand extends Command
{
    protected $signature = 'reports:cleanup-expired';

    protected $description = 'Expire old report/export artifacts and delete linked private files.';

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
