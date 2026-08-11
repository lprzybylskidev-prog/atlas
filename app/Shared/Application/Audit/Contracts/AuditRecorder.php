<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\Contracts;

use App\Shared\Application\Audit\DTOs\AuditEvent;

interface AuditRecorder
{
    public function record(AuditEvent $event): void;
}
