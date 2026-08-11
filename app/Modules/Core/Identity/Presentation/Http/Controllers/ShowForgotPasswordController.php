<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowForgotPasswordController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }
}
