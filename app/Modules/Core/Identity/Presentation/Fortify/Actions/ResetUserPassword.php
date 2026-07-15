<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Fortify\Actions;

use App\Modules\Core\Identity\Application\PasswordHistory;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Identity\Presentation\Fortify\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
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
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules($user),
        ])->validate();

        $userId = $user->internalId();
        $previousPasswordHash = $user->password;
        $hadSetFirstPassword = $user->hasSetFirstPassword();

        $this->passwordHistory->ensureNotRecentlyUsed($userId, $input['password'], $previousPasswordHash);

        $user->markFirstPasswordAsSet();

        $passwordHash = Hash::make($input['password']);

        $user->forceFill([
            'password' => $passwordHash,
        ])->save();

        if ($hadSetFirstPassword) {
            $this->passwordHistory->recordNewPassword($userId, $previousPasswordHash);
        }

        $this->passwordHistory->recordNewPassword($userId, $passwordHash);
    }
}
