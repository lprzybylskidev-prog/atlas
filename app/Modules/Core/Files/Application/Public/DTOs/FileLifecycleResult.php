<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

final readonly class FileLifecycleResult
{
    public function __construct(
        public string $publicId,
        public string $operation,
        public bool $completed,
        public ?string $createdFilePublicId = null,
    ) {}
}
