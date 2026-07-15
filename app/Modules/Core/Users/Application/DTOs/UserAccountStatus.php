<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\DTOs;

final readonly class UserAccountStatus
{
    public function __construct(
        public string $publicId,
        public string $email,
        public bool $isActive,
    ) {}
}
