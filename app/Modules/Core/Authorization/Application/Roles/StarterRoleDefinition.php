<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

final readonly class StarterRoleDefinition
{
    /**
     * @param  list<string>  $permissionNames
     */
    public function __construct(
        public StarterRoleName $name,
        public array $permissionNames,
    ) {}
}
