<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\Enums;

enum AuditSource: string
{
    case Admin = 'admin';
    case AdminUi = 'admin-ui';
    case Application = 'application';
    case Cli = 'cli';
    case E2e = 'e2e';
    case Global = 'global';
    case Http = 'http';
    case Manual = 'manual';
    case Queue = 'queue';
    case Scheduled = 'scheduled';
    case Scheduler = 'scheduler';
    case Settings = 'settings';
    case System = 'system';
    case Test = 'test';
    case Ui = 'ui';
    case Web = 'web';
}
