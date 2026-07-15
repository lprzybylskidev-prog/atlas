<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contracts;

use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationRequest;

interface ModuleDeactivationGuard
{
    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment;
}
