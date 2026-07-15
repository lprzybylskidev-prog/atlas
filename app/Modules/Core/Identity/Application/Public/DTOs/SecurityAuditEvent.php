<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\DTOs;

final readonly class SecurityAuditEvent
{
    /**
     * @param  array<string, int|string|bool|null>  $metadata
     */
    public function __construct(
        public string $module,
        public string $action,
        public string $result,
        public string $source,
        public ?string $actorPublicId,
        public ?string $targetPublicId,
        public ?string $reason,
        public array $metadata = [],
    ) {}
}
