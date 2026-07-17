<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseAuditRecorder implements AuditRecorder
{
    private const IMPERSONATION_ACTOR_PUBLIC_ID = 'atlas_impersonation_actor_public_id';

    private const IMPERSONATION_USER_PUBLIC_ID = 'atlas_impersonation_user_public_id';

    private const IMPERSONATION_SESSION_ID = 'atlas_impersonation_session_id';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function record(AuditEvent $event): void
    {
        $publicId = (string) Str::ulid();
        $occurredAt = now();
        $actualActorPublicId = $event->actualActorPublicId;
        $impersonatedUserPublicId = $event->impersonatedUserPublicId;
        $impersonationSessionId = $event->impersonationSessionId;

        if (request()->hasSession()) {
            $session = request()->session();

            if ($actualActorPublicId === null && is_string($session->get(self::IMPERSONATION_ACTOR_PUBLIC_ID))) {
                $actualActorPublicId = $session->get(self::IMPERSONATION_ACTOR_PUBLIC_ID);
            }

            if ($impersonatedUserPublicId === null && is_string($session->get(self::IMPERSONATION_USER_PUBLIC_ID))) {
                $impersonatedUserPublicId = $session->get(self::IMPERSONATION_USER_PUBLIC_ID);
            }

            if ($impersonationSessionId === null && is_string($session->get(self::IMPERSONATION_SESSION_ID))) {
                $impersonationSessionId = $session->get(self::IMPERSONATION_SESSION_ID);
            }
        }

        $this->db->table(DatabaseTable::AUDIT_EVENTS)->insert([
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
            'correlation_id' => $event->correlationId,
            'reason' => $event->reason,
            'before_values' => $this->json($event->before),
            'after_values' => $this->json($event->after),
            'metadata' => $this->json($event->metadata),
            'is_security' => $event->security,
        ]);

        if (! $event->security) {
            return;
        }

        $this->db->table(DatabaseTable::AUDIT_SECURITY_EVENTS)->insert([
            'audit_event_public_id' => $publicId,
            'occurred_at' => $occurredAt,
            'category' => $event->securityCategory ?? $this->categoryFromAction($event->action),
            'action' => $event->action,
            'result' => $event->result,
            'actor_public_id' => $event->actorPublicId,
            'target_public_id' => $event->targetPublicId,
            'team_public_id' => $event->teamPublicId,
            'correlation_id' => $event->correlationId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function categoryFromAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'login') => 'authentication',
            str_contains($action, 'password') => 'password',
            str_contains($action, 'mfa') => 'mfa',
            str_contains($action, 'session') => 'session',
            str_contains($action, 'role'), str_contains($action, 'permission'), str_contains($action, 'team') => 'authorization',
            default => 'security',
        };
    }
}
