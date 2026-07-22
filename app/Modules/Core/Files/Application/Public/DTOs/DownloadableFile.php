<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

final readonly class DownloadableFile
{
    public function __construct(
        public string $publicId,
        public string $disk,
        public string $path,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {}
}
