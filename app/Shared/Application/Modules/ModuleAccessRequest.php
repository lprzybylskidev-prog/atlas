<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;

final readonly class ModuleAccessRequest
{
    public function __construct(
        public string $moduleKey,
        public ?int $activeTeamId = null,
        public ?string $requiredPermission = null,
    ) {
        if (trim($moduleKey) === '') {
            throw new InvalidArgumentException('Module key must be a non-empty string.');
        }

        if ($requiredPermission !== null && trim($requiredPermission) === '') {
            throw new InvalidArgumentException('Required permission must be null or a non-empty string.');
        }
    }
}
