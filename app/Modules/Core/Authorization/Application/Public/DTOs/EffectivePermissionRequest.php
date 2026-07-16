<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\DTOs;

use InvalidArgumentException;

final readonly class EffectivePermissionRequest
{
    public function __construct(
        public string $userPublicId,
        public string $permission,
        public ?string $teamPublicId,
    ) {
        if (trim($userPublicId) === '' || trim($permission) === '') {
            throw new InvalidArgumentException('Effective permission requests require a user public ID and permission.');
        }
    }
}
