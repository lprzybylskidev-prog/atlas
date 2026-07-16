<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Enums;

enum TeamSettingKey: string
{
    case DefaultLocale = 'team.default_locale';
    case DefaultTheme = 'team.default_theme';
}
