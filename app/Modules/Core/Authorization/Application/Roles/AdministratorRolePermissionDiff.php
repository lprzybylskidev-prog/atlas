<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

final readonly class AdministratorRolePermissionDiff
{
    /**
     * @param  list<string>  $missingPermissionNames
     * @param  list<string>  $existingPermissionNames
     */
    public function __construct(
        public array $missingPermissionNames,
        public array $existingPermissionNames,
    ) {}

    public function hasMissingPermissions(): bool
    {
        return $this->missingPermissionNames !== [];
    }
}
