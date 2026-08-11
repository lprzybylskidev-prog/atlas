<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit\DTOs;

final readonly class AuditActorContext
{
    public function __construct(
        public ?string $actorPublicId = null,
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
