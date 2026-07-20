<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\DTOs;

use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use InvalidArgumentException;

final readonly class AuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $module,
        public string $action,
        public string $result,
        public string $source,
        public ?string $actorPublicId = null,
        public ?string $actualActorPublicId = null,
        public ?string $impersonatedUserPublicId = null,
        public ?string $impersonationSessionId = null,
        public ?string $targetType = null,
        public ?string $targetPublicId = null,
        public ?string $aggregateType = null,
        public ?string $aggregatePublicId = null,
        public ?string $teamPublicId = null,
        public ?string $correlationId = null,
        public ?string $reason = null,
        public array $before = [],
        public array $after = [],
        public array $metadata = [],
        public bool $security = false,
        public ?SecurityAuditCategory $securityCategory = null,
    ) {
        if ($this->security && $this->securityCategory === null) {
            throw new InvalidArgumentException('Security audit events require an explicit security category.');
        }

        if (! $this->security && $this->securityCategory !== null) {
            throw new InvalidArgumentException('Only security audit events may define a security category.');
        }
    }
}
