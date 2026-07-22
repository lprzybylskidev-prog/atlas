<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

use App\Modules\Core\Files\Application\Enums\FileScanState;

final readonly class StoredFile
{
    public function __construct(
        public string $publicId,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksumSha256,
        public FileScanState $scanState,
        public bool $deduplicated = false,
        public ?int $internalId = null,
    ) {}
}
