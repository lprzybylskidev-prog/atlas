<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

final class RequestPasswordResetLinkController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            Fortify::email() => ['required', 'string', 'email'],
        ]);

        $email = Str::lower($request->string(Fortify::email())->toString());
        $passwordBroker = config('fortify.passwords');
        $passwordBroker = is_string($passwordBroker) ? $passwordBroker : null;

        Password::broker($passwordBroker)->sendResetLink([
            Fortify::email() => $email,
        ]);

        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
