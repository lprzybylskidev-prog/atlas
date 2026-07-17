<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\DTOs;

final readonly class FileMaintenanceResult
{
    public function __construct(
        public int $deletedTemporaryFiles,
        public int $failedTemporaryDeletes,
    ) {}
}
