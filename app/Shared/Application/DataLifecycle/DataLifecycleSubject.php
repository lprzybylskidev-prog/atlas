<?php

declare(strict_types=1);

namespace App\Shared\Application\DataLifecycle;

use InvalidArgumentException;

final readonly class DataLifecycleSubject
{
    public function __construct(
        public string $type,
        public string $identifier,
    ) {
        if (trim($type) === '' || trim($identifier) === '') {
            throw new InvalidArgumentException('Data lifecycle subject fields must be non-empty strings.');
        }
    }
}
