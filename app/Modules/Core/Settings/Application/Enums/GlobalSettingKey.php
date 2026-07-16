<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Enums;

enum GlobalSettingKey: string
{
    case DefaultLocale = 'global.default_locale';
    case DefaultTheme = 'global.default_theme';
}
