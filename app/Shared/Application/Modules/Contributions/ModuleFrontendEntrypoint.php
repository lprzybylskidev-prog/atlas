<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModuleFrontendEntrypoint
{
    public function __construct(
        public string $name,
        public string $component,
    ) {
        if (trim($name) === '' || trim($component) === '') {
            throw new InvalidArgumentException('Module frontend entrypoint fields must be non-empty strings.');
        }
    }
}
