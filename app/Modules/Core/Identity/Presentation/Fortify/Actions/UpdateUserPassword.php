<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Fortify\Actions;

use App\Modules\Core\Identity\Application\PasswordHistory;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Identity\Presentation\Fortify\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly PasswordHistory $passwordHistory,
    ) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules($user),
        ], [
            'current_password.current_password' => __('auth.password_current_mismatch'),
        ])->validateWithBag('updatePassword');

        $userId = $user->internalId();
        $previousPasswordHash = $user->password;

        $this->passwordHistory->ensureNotRecentlyUsed($userId, $input['password'], $previousPasswordHash);

        $passwordHash = Hash::make($input['password']);

        $user->forceFill([
            'password' => $passwordHash,
        ])->save();

        $this->passwordHistory->recordNewPassword($userId, $previousPasswordHash);
        $this->passwordHistory->recordNewPassword($userId, $passwordHash);
    }
}
