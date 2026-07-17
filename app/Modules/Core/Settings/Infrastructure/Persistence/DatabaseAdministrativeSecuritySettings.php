<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Infrastructure\Persistence;

use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Public\Contracts\AdministrativeSecuritySettings;

final readonly class DatabaseAdministrativeSecuritySettings implements AdministrativeSecuritySettings
{
    public function __construct(
        private SettingsStore $settings,
    ) {}

    public function adminModeInactivityTimeoutMinutes(): int
    {
        return $this->integer(SecuritySettingKey::AdministrativeModeIdleTimeoutMinutes, 30);
    }

    public function adminModeAbsoluteLifetimeMinutes(): int
    {
        return $this->integer(SecuritySettingKey::AdministrativeModeAbsoluteLifetimeMinutes, 240);
    }

    public function adminHighRiskTimeoutMinutes(): int
    {
        return $this->integer(SecuritySettingKey::AdministrativeHighRiskTimeoutMinutes, 5);
    }

    public function mfaRequired(): bool
    {
        return $this->settings->getSecurity(SecuritySettingKey::MfaRequired) === true;
    }

    private function integer(SecuritySettingKey $key, int $fallback): int
    {
        $value = $this->settings->getSecurity($key);

        return is_int($value) ? $value : $fallback;
    }
}
