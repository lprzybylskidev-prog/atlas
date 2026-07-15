<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions\Contracts;

use App\Shared\Application\Modules\Contributions\ModuleHealthCheckDefinition;

interface ModuleHealthCheckContribution
{
    /**
     * @return list<ModuleHealthCheckDefinition>
     */
    public function healthChecks(): array;
}
