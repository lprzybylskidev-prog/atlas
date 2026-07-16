<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use InvalidArgumentException;

final readonly class ModuleAccessRequest
{
    public function __construct(
        public string $moduleKey,
        public ?int $activeTeamId = null,
        public ?string $activeTeamPublicId = null,
        public ?string $userPublicId = null,
        public ?string $requiredPermission = null,
    ) {
        if (trim($moduleKey) === '') {
            throw new InvalidArgumentException('Module key must be a non-empty string.');
        }

        if ($activeTeamPublicId !== null && trim($activeTeamPublicId) === '') {
            throw new InvalidArgumentException('Active team public ID must be null or a non-empty string.');
        }

        if ($userPublicId !== null && trim($userPublicId) === '') {
            throw new InvalidArgumentException('User public ID must be null or a non-empty string.');
        }

        if ($requiredPermission !== null && trim($requiredPermission) === '') {
            throw new InvalidArgumentException('Required permission must be null or a non-empty string.');
        }
    }
}
