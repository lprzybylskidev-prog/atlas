<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions\Contracts;

use App\Shared\Application\Modules\Contributions\ModuleMenuItem;

interface ModuleMenuContribution
{
    /**
     * @return list<ModuleMenuItem>
     */
    public function menuItems(): array;
}
