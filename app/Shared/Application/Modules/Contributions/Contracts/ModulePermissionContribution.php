<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions\Contracts;

use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

interface ModulePermissionContribution
{
    /**
     * @return list<ModulePermissionDefinition>
     */
    public function permissions(): array;
}
