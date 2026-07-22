<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Shared\Presentation\Support\FlashMessage;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UpdateUserAccountController
{
    public function __construct(
        private UserCredentialAccountDirectory $accounts,
    ) {}

    public function __invoke(Request $request, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $this->availableEmailRule($user)],
            'account_sensitivity' => ['required', 'string', 'in:normal,sensitive,technical,service,integration'],
        ]);
        $validated = is_array($validated) ? $validated : [];

        $updated = $this->accounts->updateIdentity(
            publicId: $user,
            name: $this->stringValue($validated, 'name'),
            email: $this->stringValue($validated, 'email'),
            accountSensitivity: $this->stringValue($validated, 'account_sensitivity'),
        );

        if ($updated === null) {
            abort(404);
        }

        return redirect()->route('admin.users.edit', ['user' => $user])->with('flash.messages', [
            FlashMessage::success('flash.users.updated'),
        ]);
    }

    /**
     * @param  array<mixed>  $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function availableEmailRule(string $currentUserPublicId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentUserPublicId): void {
            if (! is_string($value) || ! $this->accounts->emailExists($value, $currentUserPublicId)) {
                return;
            }

            $fail('The '.$attribute.' has already been taken.');
        };
    }
}
