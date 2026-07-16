<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

final readonly class OnboardingPackageDefinition
{
    /**
     * @param  list<string>  $initialRoleNames
     * @param  list<string>  $directPermissionNames
     * @param  list<string>  $templatePermissionNames
     */
    public function __construct(
        public string $publicId,
        public string $teamPublicId,
        public string $teamName,
        public string $name,
        public string $label,
        public array $initialRoleNames,
        public array $directPermissionNames,
        public array $templatePermissionNames,
    ) {}
}
