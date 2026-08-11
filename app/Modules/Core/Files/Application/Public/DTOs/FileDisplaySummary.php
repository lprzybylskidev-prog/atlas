<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

final readonly class FileDisplaySummary
{
    public function __construct(
        public int $internalId,
        public string $publicId,
        public string $originalName,
    ) {}
}
