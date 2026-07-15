<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;

interface SecurityAuditRecorder
{
    public function record(SecurityAuditEvent $event): void;
}
