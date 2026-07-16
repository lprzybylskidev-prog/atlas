<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Settings;

use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

final class SettingValueValidator
{
    /**
     * @param  GlobalSettingKey|TeamSettingKey|UserSettingKey|SecuritySettingKey  $key
     */
    public function validate(UnitEnum $key, mixed $value): mixed
    {
        return match ($key) {
            GlobalSettingKey::DefaultLocale,
            TeamSettingKey::DefaultLocale,
            UserSettingKey::UiLocale => $this->nullableLocale($key, $value),

            GlobalSettingKey::DefaultTheme,
            TeamSettingKey::DefaultTheme,
            UserSettingKey::Theme => $this->nullableTheme($key, $value),

            UserSettingKey::NotificationPreferences => $this->notificationPreferences($value),
            UserSettingKey::DefaultTeamPublicId => $this->nullablePublicId($key, $value),
            UserSettingKey::TableViewPreferences,
            UserSettingKey::DashboardPreferences => $this->arrayValue($key, $value),
            UserSettingKey::AccessibilityPreferences => $this->accessibilityPreferences($value),

            SecuritySettingKey::SessionIdleTimeoutMinutes,
            SecuritySettingKey::PasswordConfirmationTimeoutMinutes => $this->integerRange($key, $value, 5, 1440),
            SecuritySettingKey::MfaRequired => $this->boolean($key, $value),
        };
    }

    private function nullableLocale(UnitEnum $key, mixed $value): ?string
    {
        if ($value === null && ! $key instanceof GlobalSettingKey) {
            return null;
        }

        if ($value === 'pl' || $value === 'en') {
            return $value;
        }

        throw new InvalidArgumentException(sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    private function nullableTheme(UnitEnum $key, mixed $value): ?string
    {
        if ($value === null && ! $key instanceof GlobalSettingKey) {
            return null;
        }

        if ($value === 'light' || $value === 'dark') {
            return $value;
        }

        throw new InvalidArgumentException(sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    /**
     * @return array{database: bool, mail: bool}
     */
    private function notificationPreferences(mixed $value): array
    {
        $preferences = $this->stringKeyedArray($value, 'Notification preferences must be an object.');

        return [
            'database' => $this->optionalBoolean($preferences, 'database', true),
            'mail' => $this->optionalBoolean($preferences, 'mail', true),
        ];
    }

    private function nullablePublicId(UnitEnum $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(UnitEnum $key, mixed $value): array
    {
        return $this->stringKeyedArray($value, sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    /**
     * @return array{reduced_motion: bool, high_contrast: bool}
     */
    private function accessibilityPreferences(mixed $value): array
    {
        $preferences = $this->stringKeyedArray($value, 'Accessibility preferences must be an object.');

        return [
            'reduced_motion' => $this->optionalBoolean($preferences, 'reduced_motion', false),
            'high_contrast' => $this->optionalBoolean($preferences, 'high_contrast', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $value, string $message): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException($message);
        }

        $safe = [];

        foreach ($value as $key => $entry) {
            if (! is_string($key)) {
                throw new InvalidArgumentException($message);
            }

            $safe[$key] = $entry;
        }

        return $safe;
    }

    private function integerRange(UnitEnum $key, mixed $value, int $min, int $max): int
    {
        if (is_int($value) && $value >= $min && $value <= $max) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    private function boolean(UnitEnum $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf('Invalid value for setting [%s].', $this->keyName($key)));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function optionalBoolean(array $value, string $key, bool $default): bool
    {
        return is_bool($value[$key] ?? null) ? $value[$key] : $default;
    }

    private function keyName(UnitEnum $key): string
    {
        return $key instanceof BackedEnum && is_string($key->value) ? $key->value : $key->name;
    }
}
