<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\Contracts;

interface AdministratorAccessLookup
{
    public function hasAdministratorLevelAccess(string $userPublicId): bool;
}
