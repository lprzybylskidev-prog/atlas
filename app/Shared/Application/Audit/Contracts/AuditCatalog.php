<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\Contracts;

use App\Shared\Application\Audit\DTOs\AuditEvent;

interface AuditCatalog
{
    public function assertRegistered(AuditEvent $event): void;
}
