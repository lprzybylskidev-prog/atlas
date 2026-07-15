<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contributions\Contracts;

use App\Shared\Application\Modules\Contributions\ModuleScheduledTask;

interface ModuleScheduleContribution
{
    /**
     * @return list<ModuleScheduledTask>
     */
    public function scheduledTasks(): array;
}
