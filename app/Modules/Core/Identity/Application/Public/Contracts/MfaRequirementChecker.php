<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\MfaRequirementContext;

interface MfaRequirementChecker
{
    public function isRequired(MfaRequirementContext $context): bool;
}
