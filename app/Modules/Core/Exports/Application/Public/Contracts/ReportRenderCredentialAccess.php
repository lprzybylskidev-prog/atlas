<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\Contracts;

use App\Modules\Core\Exports\Application\Public\DTOs\ResolvedReportRenderCredential;

interface ReportRenderCredentialAccess
{
    public function resolve(string $token): ResolvedReportRenderCredential;

    public function consume(string $credentialPublicId): void;
}
