<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Optional\Reports\Application\Public\DTOs\ReportExportDispatchResult;

interface ReportExportGenerationDispatcher
{
    public function dispatch(string $requestPublicId, string $actorPublicId, ?string $teamPublicId): string;

    public function dispatchSnapshot(ReportExportRequestSnapshot $snapshot): ReportExportDispatchResult;
}
