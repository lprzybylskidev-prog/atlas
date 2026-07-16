<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use InvalidArgumentException;

final readonly class ApplyOnboardingPackageToUser
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
        private PermissionRoleStore $store,
        private SecurityAuditRecorder $audit,
    ) {}

    public function apply(
        string $packageName,
        string $userPublicId,
        string $teamPublicId,
        ?string $actorPublicId,
        bool $duringUserCreation = false,
    ): void {
        if (! $duringUserCreation) {
            throw new InvalidArgumentException('Onboarding packages may only be applied during user creation.');
        }

        $package = $this->packages->get($packageName);

        if (! $package instanceof OnboardingPackageDefinition) {
            throw new InvalidArgumentException(sprintf('Onboarding package [%s] is not registered.', $packageName));
        }

        if ($this->store->userHasOnboardingPackage($userPublicId)) {
            throw new InvalidArgumentException('Onboarding packages are one-time presets and cannot be applied again.');
        }

        foreach ($package->initialRoleNames as $roleName) {
            $this->store->assignRoleToUserInTeam($userPublicId, $teamPublicId, $roleName);
        }

        if ($package->directPermissionNames !== []) {
            $this->store->assignPermissionsToUserInTeam($userPublicId, $teamPublicId, $package->directPermissionNames);
        }

        $this->store->recordUserOnboardingPackage($userPublicId, $teamPublicId, $package->name);

        $this->audit->record(new SecurityAuditEvent(
            module: 'authorization',
            action: 'authorization.user_onboarding_package_applied',
            result: 'succeeded',
            source: 'application',
            actorPublicId: $actorPublicId,
            targetPublicId: $userPublicId,
            reason: 'User creation onboarding package',
            metadata: [
                'package' => $package->name,
                'team_public_id' => $teamPublicId,
            ],
        ));
    }
}
