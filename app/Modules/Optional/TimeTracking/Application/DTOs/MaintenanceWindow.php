<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class MaintenanceWindow
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $kind,
        public string $status,
    ) {}
}
