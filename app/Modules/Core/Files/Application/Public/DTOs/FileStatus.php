<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

use App\Modules\Core\Files\Application\Public\Enums\FileScanState;

final readonly class FileStatus
{
    public function __construct(
        public string $publicId,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public FileScanState $scanState,
        public bool $deleted,
    ) {}
}
