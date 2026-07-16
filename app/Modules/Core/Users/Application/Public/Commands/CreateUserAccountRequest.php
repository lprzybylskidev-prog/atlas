<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Public\Commands;

final readonly class CreateUserAccountRequest
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
