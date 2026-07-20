<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Runtime;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditActorContextProvider;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditActorContext;

final readonly class NullAuditActorContextProvider implements AuditActorContextProvider
{
    public function current(): AuditActorContext
    {
        return AuditActorContext::empty();
    }
}
