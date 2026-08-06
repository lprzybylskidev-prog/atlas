<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

final readonly class ActiveWorkSession
{
    public function __construct(
        public int $id,
        public string $publicId,
    ) {}
}
