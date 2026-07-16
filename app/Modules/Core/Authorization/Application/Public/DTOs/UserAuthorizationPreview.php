<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\DTOs;

final readonly class UserAuthorizationPreview
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     */
    public function __construct(
        public string $userPublicId,
        public array $roleNames,
        public array $directPermissionNames,
    ) {}
}
