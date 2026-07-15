<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions;

use InvalidArgumentException;

final readonly class ModuleScheduledTask
{
    public function __construct(
        public string $name,
        public string $command,
        public string $frequency,
        public bool $requiresActiveModule = true,
    ) {
        if (trim($name) === '' || trim($command) === '' || trim($frequency) === '') {
            throw new InvalidArgumentException('Module scheduled-task fields must be non-empty strings.');
        }
    }
}
