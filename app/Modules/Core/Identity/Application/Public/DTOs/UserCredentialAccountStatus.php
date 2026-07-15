<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class UserCredentialAccountStatus
{
    public function __construct(
        public string $publicId,
        public string $email,
        public bool $isActive,
    ) {}
}
