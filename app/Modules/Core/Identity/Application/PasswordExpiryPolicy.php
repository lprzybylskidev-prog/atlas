<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserPasswordExpiration;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Settings\Application\Public\Contracts\PasswordSecuritySettings;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class PasswordExpiryPolicy implements UserPasswordExpiration
{
    public function __construct(private PasswordSecuritySettings $settings) {}

    public function expiresAt(User $user): ?CarbonImmutable
    {
        $changedAt = $user->getAttribute('password_changed_at') ?? $user->getAttribute('first_password_set_at');

        return $this->expiresAtFromValue($changedAt);
    }

    public function expiresAtForUserId(int $userId): ?CarbonImmutable
    {
        $record = DB::table(DatabaseTable::USERS)
            ->where('id', $userId)
            ->first(['password_changed_at', 'first_password_set_at']);

        if (! is_object($record)) {
            return null;
        }

        return $this->expiresAtFromValue($record->password_changed_at ?? $record->first_password_set_at ?? null);
    }

    public function expired(User $user): bool
    {
        $expiresAt = $this->expiresAt($user);

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function expiresAfterDays(): int
    {
        return $this->settings->passwordExpiresAfterDays();
    }

    private function expiresAtFromValue(mixed $changedAt): ?CarbonImmutable
    {
        if (! $changedAt instanceof DateTimeInterface) {
            return null;
        }

        return CarbonImmutable::instance($changedAt)->addDays($this->expiresAfterDays());
    }
}
