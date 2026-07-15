<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Modules\Core\Identity\Application\Public\DTOs\SecurityAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseSecurityAuditRecorder implements SecurityAuditRecorder
{
    public function record(SecurityAuditEvent $event): void
    {
        DB::table('security_audit_events')->insert([
            'public_id' => (string) Str::ulid(),
            'occurred_at' => now(),
            'module' => $event->module,
            'action' => $event->action,
            'result' => $event->result,
            'source' => $event->source,
            'actor_public_id' => $event->actorPublicId,
            'target_public_id' => $event->targetPublicId,
            'reason' => $event->reason,
            'metadata' => json_encode($event->metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
