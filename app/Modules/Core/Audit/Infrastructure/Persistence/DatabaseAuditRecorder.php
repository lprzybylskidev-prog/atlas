<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditEventLookup;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEventSummary;
use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditActorContextProvider;
use App\Shared\Application\Audit\Contracts\AuditCatalog;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Infrastructure\Observability\SensitiveDataRedactor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

final readonly class DatabaseAuditRecorder implements AuditEventLookup, AuditRecorder
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditActorContextProvider $actorContext,
        private AuditCatalog $catalog,
        private SensitiveDataRedactor $redactor = new SensitiveDataRedactor,
    ) {}

    public function record(AuditEvent $event): void
    {
        $this->catalog->assertRegistered($event);
        $publicId = (string) Str::ulid();
        $occurredAt = now();
        $context = $this->actorContext->current();
        $actorPublicId = $event->actorPublicId ?? $context->actorPublicId;
        $actualActorPublicId = $event->actualActorPublicId;
        $impersonatedUserPublicId = $event->impersonatedUserPublicId;
        $impersonationSessionId = $event->impersonationSessionId;
        $correlationId = $event->correlationId ?? $context->correlationId;

        $actualActorPublicId ??= $context->actualActorPublicId;
        $impersonatedUserPublicId ??= $context->impersonatedUserPublicId;
        $impersonationSessionId ??= $context->impersonationSessionId;

        $this->db->transaction(function () use ($event, $publicId, $occurredAt, $actorPublicId, $actualActorPublicId, $impersonatedUserPublicId, $impersonationSessionId, $correlationId): void {
            $result = $event->result;
            $this->db->table(AuditDatabaseTable::AUDIT_EVENTS)->insert([
                'public_id' => $publicId,
                'occurred_at' => $occurredAt,
                'module' => $event->module,
                'action' => $event->action,
                'result' => $result,
                'source' => $event->source,
                'actor_public_id' => $actorPublicId,
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
                'result' => $result,
                'actor_public_id' => $actorPublicId,
                'target_public_id' => $event->targetPublicId,
                'team_public_id' => $event->teamPublicId,
                'correlation_id' => $correlationId,
            ]);
        });
    }

    public function recentForModuleAggregateOrTarget(string $module, string $publicId, int $limit = 20): array
    {
        if ($module === '' || $publicId === '' || $limit < 1) {
            return [];
        }

        return array_values(array_map(
            fn (object $row): AuditEventSummary => new AuditEventSummary(
                publicId: $this->string($row->public_id ?? null),
                occurredAt: $this->string($row->occurred_at ?? null),
                action: $this->string($row->action ?? null),
                result: $this->string($row->result ?? null),
                actorPublicId: $this->string($row->actor_public_id ?? null),
                targetPublicId: $this->string($row->target_public_id ?? null),
                reason: $this->string($row->reason ?? null),
            ),
            $this->db->table(AuditDatabaseTable::AUDIT_EVENTS)
                ->where('module', $module)
                ->where(static function (Builder $query) use ($publicId): void {
                    $query
                        ->where('aggregate_public_id', $publicId)
                        ->orWhere('target_public_id', $publicId);
                })
                ->orderByDesc('occurred_at')
                ->limit($limit)
                ->get(['public_id', 'occurred_at', 'action', 'result', 'actor_public_id', 'target_public_id', 'reason'])
                ->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
