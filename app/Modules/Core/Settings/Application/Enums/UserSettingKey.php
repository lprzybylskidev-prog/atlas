<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Enums;

enum UserSettingKey: string
{
    case UiLocale = 'user.ui.locale';
    case Theme = 'user.ui.theme';
    case NotificationPreferences = 'user.notifications.preferences';
    case DefaultTeamPublicId = 'user.teams.default_public_id';
    case TableViewPreferences = 'user.tables.preferences';
    case DashboardPreferences = 'user.dashboard.preferences';
    case AccessibilityPreferences = 'user.accessibility.preferences';
}
