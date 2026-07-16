<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserOnboardingPackageApplier;

final readonly class PublicUserOnboardingPackageApplier implements UserOnboardingPackageApplier
{
    public function __construct(
        private ApplyOnboardingPackageToUser $applier,
    ) {}

    public function applyDuringUserCreation(string $packageName, string $userPublicId, string $teamPublicId, ?string $actorPublicId): void
    {
        $this->applier->apply(
            packageName: $packageName,
            userPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            actorPublicId: $actorPublicId,
            duringUserCreation: true,
        );
    }
}
