<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Public\Contracts\OnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Public\DTOs\OnboardingPackagePreview;

final readonly class PublicOnboardingPackageDirectory implements OnboardingPackageDirectory
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
    ) {}

    public function all(): array
    {
        return array_map(static fn (OnboardingPackageDefinition $package): OnboardingPackagePreview => new OnboardingPackagePreview(
            name: $package->name,
            publicId: $package->publicId,
            teamPublicId: $package->teamPublicId,
            teamName: $package->teamName,
            label: $package->label,
            initialRoleNames: $package->initialRoleNames,
            directPermissionNames: $package->directPermissionNames,
            templatePermissionNames: $package->templatePermissionNames,
        ), $this->packages->all());
    }
}
