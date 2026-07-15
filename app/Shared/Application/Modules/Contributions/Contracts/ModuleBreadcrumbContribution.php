<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions\Contracts;

use App\Shared\Application\Modules\Contributions\ModuleBreadcrumbDefinition;

interface ModuleBreadcrumbContribution
{
    /**
     * @return list<ModuleBreadcrumbDefinition>
     */
    public function breadcrumbs(): array;
}
