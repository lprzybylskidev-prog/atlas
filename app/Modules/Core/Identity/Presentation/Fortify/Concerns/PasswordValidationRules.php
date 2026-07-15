<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Fortify\Concerns;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Closure;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /** @return array<int, Closure|Password|string> */
    protected function passwordRules(?User $user = null, ?string $email = null, ?string $name = null): array
    {
        return [
            'required',
            'string',
            Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
            $this->rejectThreeIdenticalConsecutiveCharacters(),
            $this->rejectPasswordBasedOnUserData($user, $email, $name),
            'confirmed',
        ];
    }

    private function rejectThreeIdenticalConsecutiveCharacters(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/(.)\1\1/u', $value) === 1) {
                $fail(__('auth.password_policy.no_three_identical_consecutive_characters'));
            }
        };
    }

    private function rejectPasswordBasedOnUserData(?User $user, ?string $email, ?string $name): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($user, $email, $name): void {
            if (! is_string($value)) {
                return;
            }

            $normalizedPassword = mb_strtolower($value);
            $fragments = array_filter([
                $email,
                $user?->email,
                $name,
                $user?->name,
            ], static fn (?string $fragment): bool => is_string($fragment) && trim($fragment) !== '');

            foreach ($fragments as $fragment) {
                $normalizedFragment = mb_strtolower((string) $fragment);
                $parts = preg_split('/[^a-z0-9]+/i', $normalizedFragment) ?: [];

                foreach (array_filter([...$parts, $normalizedFragment]) as $part) {
                    if (mb_strlen($part) >= 4 && str_contains($normalizedPassword, $part)) {
                        $fail(__('auth.password_policy.not_based_on_user_data'));

                        return;
                    }
                }
            }
        };
    }
}
