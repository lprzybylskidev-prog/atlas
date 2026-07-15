<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\DTOs;

final readonly class CreatedUserAccount
{
    public function __construct(
        public string $publicId,
        public string $name,
        public string $email,
        public bool $firstPasswordLinkIssued,
    ) {}
}
