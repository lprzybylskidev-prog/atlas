<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModuleHealthCheckDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public bool $readinessAffects = true,
    ) {
        if (trim($name) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Module health-check fields must be non-empty strings.');
        }
    }
}
