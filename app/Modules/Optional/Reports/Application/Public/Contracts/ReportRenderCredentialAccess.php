<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\Contracts;

use App\Modules\Optional\Reports\Application\Public\DTOs\ResolvedReportRenderCredential;

interface ReportRenderCredentialAccess
{
    public function resolve(string $token): ResolvedReportRenderCredential;

    public function consume(string $credentialPublicId): void;
}
