<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

interface ReportExportGenerationDispatcher
{
    public function dispatch(string $requestPublicId, string $actorPublicId, ?string $teamPublicId): string;
}
