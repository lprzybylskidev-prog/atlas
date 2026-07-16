<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Packages;

final readonly class RolePackageDiff
{
    /**
     * @param  list<string>  $missingPermissionNames
     * @param  list<string>  $unchangedExtraPermissionNames
     */
    public function __construct(
        public array $missingPermissionNames,
        public array $unchangedExtraPermissionNames,
    ) {}
}
