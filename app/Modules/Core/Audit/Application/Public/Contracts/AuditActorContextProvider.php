<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\Contracts;

use App\Modules\Core\Audit\Application\Public\DTOs\AuditActorContext;

interface AuditActorContextProvider
{
    public function current(): AuditActorContext;
}
