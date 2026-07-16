<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Infrastructure\Persistence;

use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;

final readonly class DatabaseSecuritySessionSettings implements SecuritySessionSettings
{
    public function __construct(
        private SettingsStore $settings,
    ) {}

    public function inactivityTimeoutMinutes(): int
    {
        $configured = $this->settings->getSecurity(SecuritySettingKey::SessionIdleTimeoutMinutes);

        return is_numeric($configured) ? max(1, (int) $configured) : 30;
    }
}
