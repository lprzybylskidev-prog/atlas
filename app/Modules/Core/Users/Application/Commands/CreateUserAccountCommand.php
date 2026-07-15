<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Commands;

final readonly class CreateUserAccountCommand
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
