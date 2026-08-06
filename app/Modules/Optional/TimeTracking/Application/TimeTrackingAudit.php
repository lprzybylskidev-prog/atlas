<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class TimeTrackingAudit
{
    public function __construct(
        private AuditRecorder $audit,
        private ConnectionInterface $database,
    ) {}

    /**
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        string $result = 'succeeded',
        ?int $actorUserId = null,
        ?int $targetUserId = null,
        ?int $teamId = null,
        ?string $targetType = null,
        ?string $targetPublicId = null,
        ?string $aggregateType = null,
        ?string $aggregatePublicId = null,
        ?string $reason = null,
        array $after = [],
        array $metadata = [],
        string $source = 'application',
    ): void {
        $this->audit->record(new AuditEvent(
            module: 'time_tracking',
            action: $action,
            result: $result,
            source: $source,
            actorPublicId: $actorUserId === null ? null : $this->userPublicId($actorUserId),
            targetType: $targetType,
            targetPublicId: $targetPublicId ?? ($targetUserId === null ? null : $this->userPublicId($targetUserId)),
            aggregateType: $aggregateType,
            aggregatePublicId: $aggregatePublicId,
            teamPublicId: $teamId === null ? null : $this->teamPublicId($teamId),
            reason: $reason,
            after: $after,
            metadata: $metadata,
        ));
    }

    /**
     * @param  array<string, mixed>  $after
     */
    public function recordCorrectionRequest(
        string $action,
        int $requestId,
        int $actorUserId,
        string $reason,
        array $after,
    ): void {
        $request = $this->database->table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)
            ->where('id', $requestId)
            ->first(['public_id', 'user_id', 'team_id']);

        $this->record(
            action: $action,
            actorUserId: $actorUserId,
            targetUserId: $request === null ? null : $this->intValue($request->user_id ?? null),
            teamId: $request === null ? null : $this->intValue($request->team_id ?? null),
            aggregateType: 'time_tracking_correction_request',
            aggregatePublicId: $request === null ? null : $this->stringValue($request->public_id ?? null),
            reason: $reason,
            after: $after,
        );
    }

    private function userPublicId(int $userId): ?string
    {
        return $this->stringValue($this->database->table(IdentityDatabaseTable::USERS)->where('id', $userId)->value('public_id'));
    }

    private function teamPublicId(int $teamId): ?string
    {
        return $this->stringValue($this->database->table(TeamsDatabaseTable::TEAMS)->where('id', $teamId)->value('public_id'));
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
