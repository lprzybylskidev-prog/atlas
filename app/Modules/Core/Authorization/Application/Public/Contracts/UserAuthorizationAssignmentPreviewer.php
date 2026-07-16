<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

use App\Modules\Core\Authorization\Application\Public\DTOs\UserAuthorizationPreview;

interface UserAuthorizationAssignmentPreviewer
{
    public function preview(string $userPublicId, string $teamPublicId): UserAuthorizationPreview;
}
