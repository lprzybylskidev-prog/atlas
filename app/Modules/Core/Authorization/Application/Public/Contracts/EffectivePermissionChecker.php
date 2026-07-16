<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionDecision;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;

interface EffectivePermissionChecker
{
    public function check(EffectivePermissionRequest $request): EffectivePermissionDecision;
}
