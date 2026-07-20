<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Public\DTOs;

final readonly class AuditActorContext
{
    public function __construct(
        public ?string $actualActorPublicId = null,
        public ?string $impersonatedUserPublicId = null,
        public ?string $impersonationSessionId = null,
        public ?string $correlationId = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
