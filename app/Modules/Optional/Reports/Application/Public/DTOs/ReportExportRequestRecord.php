<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\DTOs;

final readonly class ReportExportRequestRecord
{
    public function __construct(
        public string $publicId,
        public string $reportKey,
        public string $moduleKey,
        public string $format,
        public string $status,
        public string $requestFingerprint,
        public string $authorizationFingerprint,
    ) {}
}
