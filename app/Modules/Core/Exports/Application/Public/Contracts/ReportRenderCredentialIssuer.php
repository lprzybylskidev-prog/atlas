<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\Public\DTOs\IssuedReportRenderCredential;

interface ReportRenderCredentialIssuer
{
    public function issue(string $exportRequestPublicId): IssuedReportRenderCredential;
}
