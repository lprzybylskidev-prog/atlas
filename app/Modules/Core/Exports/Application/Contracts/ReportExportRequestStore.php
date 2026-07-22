<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportRequestRecord;
use App\Modules\Core\Files\Application\Public\DTOs\StoredFile;

interface ReportExportRequestStore
{
    public function createFromSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportRequestRecord;

    public function linkProcessRun(string $requestPublicId, string $processRunPublicId): void;

    public function markGenerating(string $requestPublicId): void;

    public function markFailed(string $requestPublicId, string $safeErrorSummary): void;

    public function publishArtifact(string $requestPublicId, StoredFile $file, string $filename, string $contentType): string;

    public function availableArtifactPublicId(string $requestPublicId): ?string;
}
