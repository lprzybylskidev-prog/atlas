<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;

final readonly class ModuleDeactivationBlocker
{
    public function __construct(
        public string $processType,
        public string $processIdentifier,
        public string $reason,
    ) {
        if (trim($processType) === '' || trim($processIdentifier) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('Module deactivation blocker fields must be non-empty strings.');
        }
    }
}
