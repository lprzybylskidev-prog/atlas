<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry;

final readonly class DefaultModuleDeactivationGuardRegistry implements ModuleDeactivationGuardRegistry
{
    /**
     * @param  iterable<ModuleDeactivationGuard>  $guards
     */
    public function __construct(private iterable $guards) {}

    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment
    {
        $blockers = [];
        $safeActions = [];

        foreach ($this->guards as $guard) {
            $assessment = $guard->assess($request);
            array_push($blockers, ...$assessment->blockers);
            array_push($safeActions, ...$assessment->safeActions);
        }

        return new ModuleDeactivationAssessment($blockers, $safeActions);
    }
}
