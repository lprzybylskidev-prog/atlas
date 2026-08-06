<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserPasswordUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UpdateUserProfilePasswordController
{
    public function __construct(
        private UserPasswordUpdater $passwords,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $this->passwords->updateAuthenticatedUserPassword($user, [
            'current_password' => $request->string('current_password')->toString(),
            'password' => $request->string('password')->toString(),
            'password_confirmation' => $request->string('password_confirmation')->toString(),
        ]);

        return redirect()->route('login');
    }
}
