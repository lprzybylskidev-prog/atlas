<?php

declare(strict_types=1);

namespace App\Shared\Application\Teams\DTOs;

final readonly class TeamOption
{
    public function __construct(
        public string $publicId,
        public string $name,
    ) {}
}
