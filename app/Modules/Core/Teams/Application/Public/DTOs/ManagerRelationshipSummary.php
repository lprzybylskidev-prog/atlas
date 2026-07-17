<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\DTOs;

final readonly class ManagerRelationshipSummary
{
    public function __construct(
        public string $publicId,
        public string $teamPublicId,
        public string $teamName,
        public string $managerUserPublicId,
        public string $managerName,
        public string $managerEmail,
        public string $reportUserPublicId,
        public string $reportName,
        public string $reportEmail,
        public string $validFrom,
        public ?string $validTo,
        public string $reason,
        public ?string $endReason,
    ) {}
}
