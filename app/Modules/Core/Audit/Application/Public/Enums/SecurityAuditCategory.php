<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\Enums;

enum SecurityAuditCategory: string
{
    case AdministrativeMode = 'administrative_mode';
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case Files = 'files';
    case Identity = 'identity';
    case Impersonation = 'impersonation';
    case Integrations = 'integrations';
    case Mfa = 'mfa';
    case Password = 'password';
    case Privacy = 'privacy';
    case QueueOperations = 'queue_operations';
    case RateLimit = 'rate_limit';
    case Security = 'security';
    case Session = 'session';
    case Settings = 'settings';
}
