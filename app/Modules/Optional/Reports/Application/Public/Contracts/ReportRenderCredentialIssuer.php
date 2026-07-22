<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\Public\DTOs\IssuedReportRenderCredential;

interface ReportRenderCredentialIssuer
{
    public function issue(string $exportRequestPublicId): IssuedReportRenderCredential;
}
