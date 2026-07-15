<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

use InvalidArgumentException;

final readonly class DataLifecycleBlocker
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
        if (trim($code) === '' || trim($message) === '') {
            throw new InvalidArgumentException('Data lifecycle blocker fields must be non-empty strings.');
        }
    }
}
