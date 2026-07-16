<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\DTOs;

final readonly class UserTeamAuthorizationAssignments
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     */
    public function __construct(
        public string $userPublicId,
        public string $teamPublicId,
        public array $roleNames,
        public array $directPermissionNames,
    ) {}
}
