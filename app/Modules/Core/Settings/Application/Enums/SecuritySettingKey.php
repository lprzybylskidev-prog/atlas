<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Enums;

enum SecuritySettingKey: string
{
    case SessionIdleTimeoutMinutes = 'security.sessions.idle_timeout_minutes';
    case PasswordConfirmationTimeoutMinutes = 'security.password_confirmation_timeout_minutes';
    case AdministrativeModeIdleTimeoutMinutes = 'security.admin_mode.idle_timeout_minutes';
    case AdministrativeModeAbsoluteLifetimeMinutes = 'security.admin_mode.absolute_lifetime_minutes';
    case AdministrativeHighRiskTimeoutMinutes = 'security.admin_mode.high_risk_timeout_minutes';
    case MfaRequired = 'security.mfa.required';
}
