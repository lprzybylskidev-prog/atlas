<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final readonly class RegisteredTableAccess
{
    public function __construct(
        public string $permission,
        public bool $teamSharingAllowed = true,
    ) {}
}
