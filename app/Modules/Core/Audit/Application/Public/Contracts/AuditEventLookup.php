<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\Contracts;

use App\Modules\Core\Audit\Application\Public\DTOs\AuditEventSummary;

interface AuditEventLookup
{
    /**
     * @return list<AuditEventSummary>
     */
    public function recentForModuleAggregateOrTarget(string $module, string $publicId, int $limit = 20): array;
}
