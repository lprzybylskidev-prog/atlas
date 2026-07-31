<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\DTOs;

use App\Modules\Core\Privacy\Application\Enums\PrivacyOperation;

final readonly class PrivacyPreviewCommand
{
    public function __construct(
        public PrivacyOperation $operation,
        public string $subjectType,
        public string $subjectIdentifier,
        public string $reason,
        public int $actorUserId,
        public ?int $teamId,
        public ?string $actorPublicId,
        public ?string $teamPublicId,
        public bool $dryRun = true,
        public ?string $correlationId = null,
    ) {}
}
