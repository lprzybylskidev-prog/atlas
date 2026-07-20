<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;

final readonly class AuditSecurityAuditRecorder implements SecurityAuditRecorder
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function record(SecurityAuditEvent $event): void
    {
        $this->audit->record(new AuditEvent(
            module: $event->module,
            action: $event->action,
            result: $event->result,
            source: $event->source,
            actorPublicId: $event->actorPublicId,
            targetType: $this->stringMetadata($event, 'target_type'),
            targetPublicId: $event->targetPublicId,
            teamPublicId: $this->stringMetadata($event, 'team_public_id'),
            correlationId: $this->stringMetadata($event, 'correlation_id'),
            reason: $event->reason,
            metadata: $event->metadata,
            security: true,
            securityCategory: $event->category,
        ));

    }

    private function stringMetadata(SecurityAuditEvent $event, string $key): ?string
    {
        $value = $event->metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
