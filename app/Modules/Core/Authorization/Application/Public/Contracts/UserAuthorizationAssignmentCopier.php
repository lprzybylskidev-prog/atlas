<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

interface UserAuthorizationAssignmentCopier
{
    public function copyForUserCreation(string $sourceUserPublicId, string $targetUserPublicId, string $teamPublicId): void;
}
