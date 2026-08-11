<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Runtime;

use App\Shared\Application\Audit\Contracts\AuditActorContextProvider;
use App\Shared\Application\Audit\DTOs\AuditActorContext;

final readonly class NullAuditActorContextProvider implements AuditActorContextProvider
{
    public function current(): AuditActorContext
    {
        return AuditActorContext::empty();
    }
}
