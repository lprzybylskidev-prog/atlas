<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class ActiveTimeLock
{
    public function __construct(
        public string $type,
        public string $publicId,
    ) {}
}
