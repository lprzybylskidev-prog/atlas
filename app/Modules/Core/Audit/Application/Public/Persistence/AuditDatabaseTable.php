<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class AuditDatabaseTable
{
    public const AUDIT_EVENTS = DatabaseSchema::CORE_AUDIT.'.audit_events';

    public const AUDIT_SECURITY_EVENTS = DatabaseSchema::CORE_AUDIT.'.audit_security_events';
}
