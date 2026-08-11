<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\Contracts;

use App\Shared\Application\Audit\DTOs\AuditActorContext;

interface AuditActorContextProvider
{
    public function current(): AuditActorContext;
}
