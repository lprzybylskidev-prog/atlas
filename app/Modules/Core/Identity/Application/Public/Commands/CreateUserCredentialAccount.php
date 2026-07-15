<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Commands;

final readonly class CreateUserCredentialAccount
{
    public function __construct(
        public string $name,
        public string $email,
        public string $internalPassword,
    ) {}
}
