<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Contracts;

use Illuminate\Http\Request;

interface HighRiskReauthenticationContinuation
{
    public function supports(Request $request): bool;

    public function validate(Request $request): void;

    public function preserve(Request $request): void;
}
