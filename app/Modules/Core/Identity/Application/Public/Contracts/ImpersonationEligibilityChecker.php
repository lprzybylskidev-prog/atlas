<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\ImpersonationEligibility;
use Illuminate\Http\Request;

interface ImpersonationEligibilityChecker
{
    public function eligibility(Request $request, string $actorPublicId, string $targetPublicId, ?string $teamPublicId = null): ImpersonationEligibility;
}
