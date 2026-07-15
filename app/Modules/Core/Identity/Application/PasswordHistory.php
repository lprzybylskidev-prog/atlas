<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application;

use App\Modules\Core\Identity\Application\Contracts\PasswordHistoryRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class PasswordHistory
{
    private const RECENT_PASSWORD_LIMIT = 10;

    public function __construct(
        private PasswordHistoryRepository $passwords,
    ) {}

    /**
     * @throws ValidationException
     */
    public function ensureNotRecentlyUsed(int $userId, string $plainPassword, ?string $currentPasswordHash = null): void
    {
        if (is_string($currentPasswordHash) && Hash::check($plainPassword, $currentPasswordHash)) {
            $this->failRecentlyUsed();
        }

        if ($this->passwords->containsRecentPassword($userId, $plainPassword, self::RECENT_PASSWORD_LIMIT)) {
            $this->failRecentlyUsed();
        }
    }

    public function recordNewPassword(int $userId, string $passwordHash): void
    {
        $this->passwords->record($userId, $passwordHash, self::RECENT_PASSWORD_LIMIT);
    }

    /**
     * @throws ValidationException
     */
    private function failRecentlyUsed(): never
    {
        throw ValidationException::withMessages([
            'password' => __('auth.password_policy.not_recently_used'),
        ]);
    }
}
