<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Users\Application\Commands\CreateUserAccountCommand;
use App\Modules\Core\Users\Application\CreateUserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StoreUserAccountController
{
    public function __construct(
        private CreateUserAccount $users,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'authorization_mode' => ['nullable', 'string', 'in:package,copy'],
            'onboarding_package' => ['nullable', 'string'],
            'copy_authorization_from_user' => ['nullable', 'string'],
        ]);
        $validated = is_array($validated) ? $validated : [];

        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $actorPublicId = data_get($request->user(), 'public_id');
        $name = $this->stringValue($validated, 'name');
        $email = $this->stringValue($validated, 'email');
        $mode = $this->nullableStringValue($validated, 'authorization_mode') ?: 'package';
        $package = $this->nullableStringValue($validated, 'onboarding_package');
        $copyFromUser = $this->nullableStringValue($validated, 'copy_authorization_from_user');

        $this->users->handle(new CreateUserAccountCommand(
            name: $name,
            email: $email,
            onboardingPackageName: $mode === 'package' && $package !== '' ? $package : null,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            copyAuthorizationFromUserPublicId: $mode === 'copy' && $copyFromUser !== '' ? $copyFromUser : null,
        ));

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account was created and the first-password link was sent.');
    }

    /**
     * @param  array<mixed>  $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<mixed>  $values
     */
    private function nullableStringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
