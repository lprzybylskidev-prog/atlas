<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EditOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
        private UserTeamAuthorizationManager $authorization,
    ) {}

    public function __invoke(string $package): Response
    {
        $definition = $this->packages->getByPublicId($package);

        if ($definition === null) {
            abort(404);
        }

        return Inertia::render('Admin/Authorization/Packages/Edit', [
            'package' => [
                'publicId' => $definition->publicId,
                'teamPublicId' => $definition->teamPublicId,
                'teamName' => $definition->teamName,
                'name' => $definition->name,
                'label' => $definition->label,
                'initialRoles' => $definition->initialRoleNames,
                'directPermissions' => $definition->directPermissionNames,
            ],
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'rolePermissionMap' => $this->authorization->rolePermissionMap(),
        ]);
    }
}
