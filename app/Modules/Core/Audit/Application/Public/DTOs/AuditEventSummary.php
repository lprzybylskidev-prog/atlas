<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\DTOs;

final readonly class AuditEventSummary
{
    public function __construct(
        public string $publicId,
        public string $occurredAt,
        public string $action,
        public string $result,
        public string $actorPublicId,
        public string $targetPublicId,
        public string $reason,
    ) {}
}
