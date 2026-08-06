<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Settings;

use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;

final class SettingsDefaults
{
    public function global(GlobalSettingKey $key): mixed
    {
        return match ($key) {
            GlobalSettingKey::DefaultLocale => 'pl',
            GlobalSettingKey::DefaultTheme => 'light',
        };
    }

    public function team(TeamSettingKey $key): mixed
    {
        return match ($key) {
            TeamSettingKey::DefaultLocale,
            TeamSettingKey::DefaultTheme => null,
        };
    }

    public function user(UserSettingKey $key): mixed
    {
        return match ($key) {
            UserSettingKey::UiLocale => null,
            UserSettingKey::Theme => null,
            UserSettingKey::DefaultTeamPublicId => null,
            UserSettingKey::TableViewPreferences => [],
            UserSettingKey::DashboardPreferences => [],
            UserSettingKey::AccessibilityPreferences => [
                'reduced_motion' => false,
                'high_contrast' => false,
            ],
        };
    }

    public function security(SecuritySettingKey $key): mixed
    {
        return match ($key) {
            SecuritySettingKey::SessionIdleTimeoutMinutes => 30,
            SecuritySettingKey::PasswordConfirmationTimeoutMinutes => 15,
            SecuritySettingKey::PasswordExpiresAfterDays => config('atlas.security.passwords.expires_after_days', 90),
            SecuritySettingKey::AdministrativeModeIdleTimeoutMinutes => 30,
            SecuritySettingKey::AdministrativeModeAbsoluteLifetimeMinutes => 240,
            SecuritySettingKey::AdministrativeHighRiskTimeoutMinutes => 5,
            SecuritySettingKey::MfaRequired => false,
        };
    }
}
