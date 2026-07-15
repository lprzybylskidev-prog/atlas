<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;

final readonly class ModuleDeactivationSafeAction
{
    public function __construct(
        public string $action,
        public string $label,
    ) {
        if (trim($action) === '' || trim($label) === '') {
            throw new InvalidArgumentException('Module deactivation safe action fields must be non-empty strings.');
        }
    }
}
