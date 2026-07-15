<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contracts;

use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleAccessState;

interface ModuleGateStateProvider
{
    public function stateFor(ModuleAccessRequest $request): ModuleAccessState;
}
