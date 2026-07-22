<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\DTOs;

final readonly class ReportExportCleanupResult
{
    public function __construct(
        public int $expiredRequests,
        public int $expiredArtifacts,
        public int $deletedFiles,
        public int $failedFileDeletes,
    ) {}
}
