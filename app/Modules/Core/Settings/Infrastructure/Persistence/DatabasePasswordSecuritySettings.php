<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Infrastructure\Persistence;

use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Public\Contracts\PasswordSecuritySettings;

final readonly class DatabasePasswordSecuritySettings implements PasswordSecuritySettings
{
    public function __construct(
        private SettingsStore $settings,
    ) {}

    public function passwordExpiresAfterDays(): int
    {
        $value = $this->settings->getSecurity(SecuritySettingKey::PasswordExpiresAfterDays);

        return is_int($value) ? $value : 90;
    }
}
