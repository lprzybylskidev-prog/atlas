<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Enums;

enum SecuritySettingKey: string
{
    case SessionIdleTimeoutMinutes = 'security.sessions.idle_timeout_minutes';
    case PasswordConfirmationTimeoutMinutes = 'security.password_confirmation_timeout_minutes';
    case MfaRequired = 'security.mfa.required';
}
