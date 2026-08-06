<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId;
use App\Modules\Core\Identity\Infrastructure\Database\Factories\UserFactory;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserEmailVerificationNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const DEFAULT_AVATAR_COLOR = '#0f766e';

    protected $table = IdentityDatabaseTable::USERS;

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
        'first_password_set_at',
        'password_changed_at',
        'is_active',
        'deactivated_at',
        'failed_login_attempts',
        'login_lock_count',
        'login_locked_until',
        'account_sensitivity',
        'avatar_color',
        'avatar_image_file_public_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'remember_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $user): void {
            $publicId = $user->getAttribute('public_id');

            if (! is_string($publicId) || $publicId === '') {
                $user->public_id = UserPublicId::new()->toString();
            }
        });

        self::saving(function (self $user): void {
            $avatarColor = $user->getAttribute('avatar_color');

            if (! is_string($avatarColor) || $avatarColor === '') {
                $user->avatar_color = self::DEFAULT_AVATAR_COLOR;
            }
        });
    }

    public function canAuthenticate(): bool
    {
        return $this->isActive() && $this->hasSetFirstPassword() && ! $this->isLoginLocked();
    }

    public function internalId(): int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->deactivated_at === null;
    }

    public function hasSetFirstPassword(): bool
    {
        return $this->first_password_set_at !== null;
    }

    public function isLoginLocked(): bool
    {
        return $this->loginLockedUntil()?->isFuture() === true;
    }

    public function loginLockedUntil(): ?Carbon
    {
        $lockedUntil = $this->getAttribute('login_locked_until');

        return $lockedUntil instanceof Carbon ? $lockedUntil : null;
    }

    public function markFirstPasswordAsSet(): void
    {
        if ($this->first_password_set_at === null) {
            $this->setAttribute('first_password_set_at', now());
        }

        if ($this->email_verified_at === null) {
            $this->setAttribute('email_verified_at', now());
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new UserEmailVerificationNotification);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_password_set_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'login_lock_count' => 'integer',
            'login_locked_until' => 'datetime',
            'account_sensitivity' => 'string',
            'password' => 'hashed',
        ];
    }
}
