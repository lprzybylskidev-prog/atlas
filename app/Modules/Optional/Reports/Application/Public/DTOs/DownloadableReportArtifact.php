<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\DTOs;

final readonly class DownloadableReportArtifact
{
    public function __construct(
        public string $artifactPublicId,
        public string $exportRequestPublicId,
        public string $filePublicId,
        public string $disk,
        public string $path,
        public string $filename,
        public string $contentType,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {}
}
