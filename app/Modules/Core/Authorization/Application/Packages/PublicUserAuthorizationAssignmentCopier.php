<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentCopier;

final readonly class PublicUserAuthorizationAssignmentCopier implements UserAuthorizationAssignmentCopier
{
    public function __construct(
        private PermissionRoleStore $store,
    ) {}

    public function copyForUserCreation(string $sourceUserPublicId, string $targetUserPublicId, string $teamPublicId): void
    {
        $this->store->copyAssignmentsBetweenUsers($sourceUserPublicId, $targetUserPublicId, $teamPublicId);
    }
}
