<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\Public\DTOs;

use DateTimeImmutable;

final readonly class IssuedReportRenderCredential
{
    public function __construct(
        public string $publicId,
        public string $token,
        public string $exportRequestPublicId,
        public DateTimeImmutable $expiresAt,
    ) {}
}
