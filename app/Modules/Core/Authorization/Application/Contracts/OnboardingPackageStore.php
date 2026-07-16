<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Contracts;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageDefinition;

interface OnboardingPackageStore
{
    /**
     * @return list<OnboardingPackageDefinition>
     */
    public function allActive(?string $teamPublicId = null): array;

    public function findByPublicId(string $publicId): ?OnboardingPackageDefinition;

    public function findActiveForTeam(string $name, string $teamPublicId): ?OnboardingPackageDefinition;

    /**
     * @param  list<string>  $initialRoleNames
     * @param  list<string>  $directPermissionNames
     * @param  list<string>  $templatePermissionNames
     */
    public function upsert(
        string $teamPublicId,
        string $name,
        string $label,
        array $initialRoleNames,
        array $directPermissionNames,
        array $templatePermissionNames,
    ): void;

    public function deactivate(string $publicId): void;
}
