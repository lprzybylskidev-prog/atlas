<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;

final readonly class ModuleDeactivationRequest
{
    public function __construct(
        public ModuleKey $moduleKey,
        public ?int $teamId,
        public string $requestedBy,
    ) {
        if ($teamId !== null && $teamId < 1) {
            throw new InvalidArgumentException('Module deactivation team ID must be positive when provided.');
        }

        if (trim($requestedBy) === '') {
            throw new InvalidArgumentException('Module deactivation requester must be a non-empty string.');
        }
    }
}
