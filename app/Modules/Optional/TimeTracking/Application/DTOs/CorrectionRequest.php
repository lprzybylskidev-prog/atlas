<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class CorrectionRequest
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $status,
    ) {}
}
