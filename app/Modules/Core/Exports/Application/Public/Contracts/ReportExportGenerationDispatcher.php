<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportDispatchResult;

interface ReportExportGenerationDispatcher
{
    public function dispatch(string $requestPublicId, string $actorPublicId, ?string $teamPublicId): string;

    public function dispatchSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportDispatchResult;
}
