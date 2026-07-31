<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use Illuminate\Http\Request;

interface HighRiskAdministrativeAuthorization
{
    public function highRiskFresh(Request $request): bool;
}
