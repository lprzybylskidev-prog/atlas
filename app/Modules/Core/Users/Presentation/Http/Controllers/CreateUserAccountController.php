<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\OnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentPreviewer;
use App\Modules\Core\Authorization\Application\Public\DTOs\UserAuthorizationPreview;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Identity\Application\Public\DTOs\UserCredentialAccountOption;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateUserAccountController
{
    public function __construct(
        private OnboardingPackageDirectory $packages,
        private UserCredentialAccountDirectory $accounts,
        private UserAuthorizationAssignmentPreviewer $authorizationPreviewer,
    ) {}

    public function __invoke(): Response
    {
        $teamPublicId = session('active_team_public_id');
        $teamPublicId = is_string($teamPublicId) ? $teamPublicId : '';

        return Inertia::render('Admin/Users/Create', [
            'packages' => array_map(static fn ($package): array => [
                'name' => $package->name,
                'label' => $package->label,
                'initialRoles' => $package->initialRoleNames,
                'directPermissions' => $package->directPermissionNames,
                'templatePermissions' => $package->templatePermissionNames,
            ], $this->packages->all()),
            'copySources' => array_map(function (UserCredentialAccountOption $user) use ($teamPublicId): array {
                $preview = $teamPublicId === ''
                    ? new UserAuthorizationPreview($user->publicId, [], [])
                    : $this->authorizationPreviewer->preview($user->publicId, $teamPublicId);

                return [
                    'publicId' => $user->publicId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $preview->roleNames,
                    'directPermissions' => $preview->directPermissionNames,
                ];
            }, $this->accounts->allOptions()),
        ]);
    }
}
