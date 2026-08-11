<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Fixtures;

use App\Modules\Core\Identity\Application\Public\Contracts\VerifiedUserFixtureBuilder;
use App\Modules\Core\Identity\Application\Public\DTOs\VerifiedUserFixture;
use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class EloquentVerifiedUserFixtureBuilder implements VerifiedUserFixtureBuilder
{
    private const VERIFIED_AT = '2026-08-01 00:00:00+00';

    public function provide(
        string $name,
        string $email,
        string $plainPassword,
        string $accountSensitivity = 'normal',
    ): VerifiedUserFixture {
        $normalizedEmail = mb_strtolower(trim($email));
        $user = User::query()->firstOrNew(['email' => $normalizedEmail]);

        $user->fill([
            'name' => trim($name),
            'password' => Hash::make($plainPassword),
            'first_password_set_at' => self::VERIFIED_AT,
            'password_changed_at' => self::VERIFIED_AT,
            'is_active' => true,
            'deactivated_at' => null,
            'failed_login_attempts' => 0,
            'login_lock_count' => 0,
            'login_locked_until' => null,
            'account_sensitivity' => $accountSensitivity,
            'avatar_color' => User::DEFAULT_AVATAR_COLOR,
            'avatar_image_file_public_id' => null,
        ]);
        $user->setAttribute('email_verified_at', self::VERIFIED_AT);
        $user->setAttribute('two_factor_secret', null);
        $user->setAttribute('two_factor_recovery_codes', null);
        $user->setAttribute('two_factor_confirmed_at', null);
        $user->setAttribute('remember_token', null);
        $user->save();

        DB::table(IdentityDatabaseTable::PASSWORD_RESET_TOKENS)->where('email', $normalizedEmail)->delete();
        DB::table(IdentityDatabaseTable::SESSIONS)->where('user_id', $user->id)->delete();
        DB::table(IdentityDatabaseTable::USER_PASSWORD_HISTORIES)->where('user_id', $user->id)->delete();

        return new VerifiedUserFixture(
            internalId: (int) $user->id,
            publicId: (string) $user->public_id,
            name: (string) $user->name,
            email: (string) $user->email,
        );
    }
}
