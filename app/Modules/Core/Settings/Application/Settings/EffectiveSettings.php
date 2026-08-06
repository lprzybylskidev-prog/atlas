<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Settings;

use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;

final readonly class EffectiveSettings
{
    public function __construct(
        private SettingsStore $store,
        private SettingsDefaults $defaults,
    ) {}

    public function locale(?int $userId = null, ?int $teamId = null, ?string $guestLocale = null): string
    {
        if ($userId !== null) {
            $userLocale = $this->store->getUser($userId, UserSettingKey::UiLocale);

            if (is_string($userLocale)) {
                return $userLocale;
            }
        }

        if ($teamId !== null) {
            $teamLocale = $this->store->getTeam($teamId, TeamSettingKey::DefaultLocale);

            if (is_string($teamLocale)) {
                return $teamLocale;
            }
        }

        if ($guestLocale === 'pl' || $guestLocale === 'en') {
            return $guestLocale;
        }

        $globalLocale = $this->store->getGlobal(GlobalSettingKey::DefaultLocale);

        if (is_string($globalLocale)) {
            return $globalLocale;
        }

        $defaultLocale = $this->defaults->global(GlobalSettingKey::DefaultLocale);

        return is_string($defaultLocale) ? $defaultLocale : 'pl';
    }

    public function theme(?int $userId = null, ?int $teamId = null, ?string $guestTheme = null): string
    {
        if ($userId !== null) {
            $userTheme = $this->store->getUser($userId, UserSettingKey::Theme);

            if (is_string($userTheme)) {
                return $userTheme;
            }
        }

        if ($teamId !== null) {
            $teamTheme = $this->store->getTeam($teamId, TeamSettingKey::DefaultTheme);

            if (is_string($teamTheme)) {
                return $teamTheme;
            }
        }

        if ($guestTheme === 'light' || $guestTheme === 'dark') {
            return $guestTheme;
        }

        $globalTheme = $this->store->getGlobal(GlobalSettingKey::DefaultTheme);

        if (is_string($globalTheme)) {
            return $globalTheme;
        }

        $defaultTheme = $this->defaults->global(GlobalSettingKey::DefaultTheme);

        return is_string($defaultTheme) ? $defaultTheme : 'light';
    }

    public function setUserLocale(int $userId, string $locale): void
    {
        $this->store->putUser($userId, UserSettingKey::UiLocale, $locale);
    }

    public function setUserTheme(int $userId, string $theme): void
    {
        $this->store->putUser($userId, UserSettingKey::Theme, $theme);
    }

    public function security(SecuritySettingKey $key): mixed
    {
        $value = $this->store->getSecurity($key);

        return $value ?? $this->defaults->security($key);
    }
}
