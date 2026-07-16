<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class BootstrapTeam
{
    public function __construct(
        public string $publicId,
        public string $name,
    ) {}
}
