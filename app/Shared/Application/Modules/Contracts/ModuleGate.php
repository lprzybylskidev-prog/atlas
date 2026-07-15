<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contracts;

use App\Shared\Application\Modules\ModuleAccessDecision;
use App\Shared\Application\Modules\ModuleAccessRequest;

interface ModuleGate
{
    public function inspect(ModuleAccessRequest $request): ModuleAccessDecision;

    public function allows(ModuleAccessRequest $request): bool;
}
