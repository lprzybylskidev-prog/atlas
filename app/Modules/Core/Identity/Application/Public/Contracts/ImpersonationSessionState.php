<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use Illuminate\Http\Request;

interface ImpersonationSessionState
{
    public function active(Request $request): bool;

    public function sessionId(Request $request): ?string;
}
