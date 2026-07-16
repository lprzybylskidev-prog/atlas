<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

interface UserOnboardingPackageApplier
{
    public function applyDuringUserCreation(string $packageName, string $userPublicId, string $teamPublicId, ?string $actorPublicId): void;
}
