<?php

declare(strict_types=1);

namespace App\Shared\Application\Security\Contracts;

use Illuminate\Http\Request;

interface AdministrativeModeState
{
    public function active(Request $request): bool;
}
