<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class CreatedUserCredentialAccount
{
    public function __construct(
        public string $publicId,
        public string $name,
        public string $email,
    ) {}
}
