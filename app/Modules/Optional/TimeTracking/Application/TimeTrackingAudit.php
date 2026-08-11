<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Database\ConnectionInterface;

final readonly class TimeTrackingAudit
{
    public function __construct(
        private AuditRecorder $audit,
        private ConnectionInterface $database,
        private UserLookup $users,
        private TeamLookup $teams,
    ) {}

    /**
     * @param  array<string, mixed>  $before
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
        array $before = [],
        array $after = [],
        array $metadata = [],
        string $source = 'application',
        ?SecurityAuditCategory $securityCategory = null,
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
            before: $before,
            after: $after,
            metadata: $metadata,
            security: $securityCategory !== null,
            securityCategory: $securityCategory,
        ));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function recordCorrectionRequest(
        string $action,
        int $requestId,
        int $actorUserId,
        string $reason,
        array $before,
        array $after,
        ?SecurityAuditCategory $securityCategory = null,
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
            before: $before,
            after: $after,
            securityCategory: $securityCategory,
        );
    }

    private function userPublicId(int $userId): ?string
    {
        return $this->users->publicIdForInternalId($userId);
    }

    private function teamPublicId(int $teamId): ?string
    {
        return $this->teams->publicIdForInternalId($teamId);
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
