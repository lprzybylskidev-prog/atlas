<?php

declare(strict_types=1);

namespace App\Shared\Application\Authorization\Contracts;

use App\Shared\Application\Authorization\DTOs\EffectivePermissionDecision;
use App\Shared\Application\Authorization\DTOs\EffectivePermissionRequest;

interface EffectivePermissionChecker
{
    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision;
}
