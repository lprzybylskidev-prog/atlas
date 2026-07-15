<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModulePermissionDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $teamScoped = true,
    ) {
        if (trim($name) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Module permission fields must be non-empty strings.');
        }
    }
}
