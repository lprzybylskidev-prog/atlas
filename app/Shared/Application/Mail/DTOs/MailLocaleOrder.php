<?php

declare(strict_types=1);

namespace App\Shared\Application\Mail\DTOs;

final readonly class MailLocaleOrder
{
    public function __construct(
        public string $effective,
        public string $secondary,
    ) {}

    /** @return array{0: string, 1: string} */
    public function all(): array
    {
        return [$this->effective, $this->secondary];
    }
}
