<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

interface ReportPrintViewAccess
{
    public function html(string $exportRequestPublicId, string $actorPublicId, ?string $activeTeamPublicId): string;
}
