<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditActorContextProvider;
use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Persistence\AuditDatabaseTable;
use App\Shared\Infrastructure\Observability\SensitiveDataRedactor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseAuditRecorder implements AuditRecorder
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditActorContextProvider $actorContext,
        private SensitiveDataRedactor $redactor = new SensitiveDataRedactor,
    ) {}

    public function record(AuditEvent $event): void
    {
        $publicId = (string) Str::ulid();
        $occurredAt = now();
        $context = $this->actorContext->current();
        $actualActorPublicId = $event->actualActorPublicId;
        $impersonatedUserPublicId = $event->impersonatedUserPublicId;
        $impersonationSessionId = $event->impersonationSessionId;
        $correlationId = $event->correlationId ?? $context->correlationId;

        $actualActorPublicId ??= $context->actualActorPublicId;
        $impersonatedUserPublicId ??= $context->impersonatedUserPublicId;
        $impersonationSessionId ??= $context->impersonationSessionId;

        $this->db->table(AuditDatabaseTable::AUDIT_EVENTS)->insert([
            'public_id' => $publicId,
            'occurred_at' => $occurredAt,
            'module' => $event->module,
            'action' => $event->action,
            'result' => $event->result,
            'source' => $event->source,
            'actor_public_id' => $event->actorPublicId,
            'actual_actor_public_id' => $actualActorPublicId,
            'impersonated_user_public_id' => $impersonatedUserPublicId,
            'impersonation_session_id' => $impersonationSessionId,
            'target_type' => $event->targetType,
            'target_public_id' => $event->targetPublicId,
            'aggregate_type' => $event->aggregateType,
            'aggregate_public_id' => $event->aggregatePublicId,
            'team_public_id' => $event->teamPublicId,
            'correlation_id' => $correlationId,
            'reason' => $event->reason === null ? null : $this->redactor->redactText($event->reason),
            'before_values' => $this->json($this->redactor->redactStringKeyedArray($event->before)),
            'after_values' => $this->json($this->redactor->redactStringKeyedArray($event->after)),
            'metadata' => $this->json($this->redactor->redactStringKeyedArray($event->metadata)),
            'is_security' => $event->security,
        ]);

        if (! $event->security) {
            return;
        }

        $this->db->table(AuditDatabaseTable::AUDIT_SECURITY_EVENTS)->insert([
            'audit_event_public_id' => $publicId,
            'occurred_at' => $occurredAt,
            'category' => $event->securityCategory?->value,
            'action' => $event->action,
            'result' => $event->result,
            'actor_public_id' => $event->actorPublicId,
            'target_public_id' => $event->targetPublicId,
            'team_public_id' => $event->teamPublicId,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
