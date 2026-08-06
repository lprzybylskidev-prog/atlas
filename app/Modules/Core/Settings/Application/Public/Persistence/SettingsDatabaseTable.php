<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class SettingsDatabaseTable
{
    public const SETTINGS_GLOBAL_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_global_values';

    public const SETTINGS_TEAM_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_team_values';

    public const SETTINGS_USER_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_user_values';

    public const SETTINGS_SECURITY_VALUES = DatabaseSchema::CORE_SETTINGS.'.settings_security_values';
}
