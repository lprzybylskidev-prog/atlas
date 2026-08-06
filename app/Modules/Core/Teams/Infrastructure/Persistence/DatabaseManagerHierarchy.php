<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerScope;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class DatabaseManagerHierarchy implements ManagerHierarchy
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function activeRelationships(?string $teamPublicId = null): array
    {
        return $this->relationshipRows(activeOnly: true, teamPublicId: $teamPublicId);
    }

    public function relationshipHistory(string $teamPublicId): array
    {
        return $this->relationshipRows(activeOnly: false, teamPublicId: $teamPublicId);
    }

    public function tree(string $teamPublicId): array
    {
        $teamId = $this->teamId($teamPublicId);
        $members = $this->activeMembers($teamId);
        $children = $this->activeChildren($teamId);
        $hasManager = [];

        foreach ($children as $reportIds) {
            foreach ($reportIds as $reportId) {
                $hasManager[$reportId] = true;
            }
        }

        $roots = [];

        foreach (array_keys($members) as $userId) {
            if (! isset($hasManager[$userId])) {
                $roots[] = $this->node($userId, $members, $children, []);
            }
        }

        return $roots;
    }

    public function previewAssign(string $teamPublicId, string $managerUserPublicId, string $reportUserPublicId): ManagerImpactPreview
    {
        [$teamId, $managerUserId, $reportUserId] = $this->resolveTeamUserIds($teamPublicId, $managerUserPublicId, $reportUserPublicId);
        $warnings = [];

        if ($managerUserId === $reportUserId) {
            $warnings[] = 'A user cannot manage themselves.';
        }

        if (! $this->hasActiveMembership($teamId, $managerUserId) || ! $this->hasActiveMembership($teamId, $reportUserId)) {
            $warnings[] = 'Both users must have active access to the selected team.';
        }

        if ($this->activeRelationshipExists($teamId, $managerUserId, $reportUserId)) {
            $warnings[] = 'This manager relationship is already active.';
        }

        if ($this->wouldCreateCycle($teamId, $managerUserId, $reportUserId)) {
            $warnings[] = 'This relationship would create a cycle.';
        }

        return new ManagerImpactPreview(
            allowed: $warnings === [],
            action: 'assign',
            affectedReportPublicIds: $this->publicIds([$reportUserId, ...$this->descendantIds($teamId, $reportUserId)]),
            warnings: $warnings,
        );
    }

    public function previewEnd(string $relationshipPublicId): ManagerImpactPreview
    {
        $relationship = $this->relationshipByPublicId($relationshipPublicId);

        if ($relationship === null || $relationship['valid_to'] !== null) {
            return new ManagerImpactPreview(false, 'end', [], ['The selected relationship is not active.']);
        }

        $reportId = $relationship['report_user_id'];
        $affected = $this->publicIds([$reportId, ...$this->descendantIds($relationship['team_id'], $reportId)]);

        return new ManagerImpactPreview(true, 'end', $affected);
    }

    public function assign(
        string $actorUserPublicId,
        string $teamPublicId,
        string $managerUserPublicId,
        string $reportUserPublicId,
        string $validFrom,
        string $reason,
    ): void {
        [$teamId, $managerUserId, $reportUserId] = $this->resolveTeamUserIds($teamPublicId, $managerUserPublicId, $reportUserPublicId);
        $actorUserId = $this->userId($actorUserPublicId);
        $validFromAt = Carbon::parse($validFrom);

        if ($managerUserId === $reportUserId) {
            throw ManagerHierarchyViolation::selfManagement();
        }

        if (! $this->hasActiveMembership($teamId, $managerUserId) || ! $this->hasActiveMembership($teamId, $reportUserId)) {
            throw ManagerHierarchyViolation::inactiveMembership();
        }

        if ($this->activeRelationshipExists($teamId, $managerUserId, $reportUserId)) {
            throw ManagerHierarchyViolation::duplicateActiveRelationship();
        }

        if ($this->wouldCreateCycle($teamId, $managerUserId, $reportUserId)) {
            throw ManagerHierarchyViolation::cycle();
        }

        $publicId = (string) Str::ulid();

        DB::transaction(static function () use ($teamId, $managerUserId, $reportUserId, $actorUserId, $validFromAt, $reason, $publicId): void {
            DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->insert([
                'public_id' => $publicId,
                'team_id' => $teamId,
                'manager_user_id' => $managerUserId,
                'report_user_id' => $reportUserId,
                'valid_from' => $validFromAt,
                'valid_to' => null,
                'created_by_user_id' => $actorUserId,
                'ended_by_user_id' => null,
                'reason' => $reason,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.manager_relationship.created', 'succeeded', 'manager_relationship', $publicId, [], [
            'managerUserPublicId' => $managerUserPublicId,
            'reportUserPublicId' => $reportUserPublicId,
            'validFrom' => $validFromAt->toISOString(),
            'reason' => $reason,
        ]);
    }

    public function end(string $actorUserPublicId, string $relationshipPublicId, string $validTo, string $reason): void
    {
        $relationship = $this->relationshipByPublicId($relationshipPublicId);

        if ($relationship === null || $relationship['valid_to'] !== null) {
            throw ManagerHierarchyViolation::missingActiveRelationship();
        }

        $actorUserId = $this->userId($actorUserPublicId);
        $validToAt = Carbon::parse($validTo);

        DB::transaction(static function () use ($relationshipPublicId, $actorUserId, $validToAt, $reason): void {
            DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
                ->where('public_id', $relationshipPublicId)
                ->whereNull('valid_to')
                ->update([
                    'valid_to' => $validToAt,
                    'ended_by_user_id' => $actorUserId,
                    'end_reason' => $reason,
                    'updated_at' => now(),
                ]);
        });

        $teamPublicId = $this->teamPublicId($relationship['team_id']);
        $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.manager_relationship.ended', 'succeeded', 'manager_relationship', $relationshipPublicId, [
            'validTo' => null,
        ], [
            'validTo' => $validToAt->toISOString(),
            'reason' => $reason,
        ]);
    }

    public function setHeadManager(
        string $actorUserPublicId,
        string $teamPublicId,
        string $userPublicId,
        bool $headManager,
        string $reason,
    ): void {
        $teamId = $this->teamId($teamPublicId);
        $userId = $this->userId($userPublicId);

        if (! $this->hasActiveMembership($teamId, $userId)) {
            throw ManagerHierarchyViolation::inactiveMembership();
        }

        $before = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->value('is_head_manager');

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->update([
                'is_head_manager' => $headManager,
                'updated_at' => now(),
            ]);

        $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.head_manager.updated', 'succeeded', 'team_user_assignment', $userPublicId, [
            'headManager' => (bool) $before,
        ], [
            'headManager' => $headManager,
            'reason' => $reason,
        ]);
    }

    public function scopeFor(string $teamPublicId, string $managerUserPublicId): ManagerScope
    {
        $teamId = $this->teamId($teamPublicId);
        $managerUserId = $this->userId($managerUserPublicId);
        $headManager = (bool) DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $managerUserId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->value('is_head_manager');
        $ids = $headManager
            ? $this->descendantIds($teamId, $managerUserId)
            : ($this->activeChildren($teamId)[$managerUserId] ?? []);

        return new ManagerScope($teamPublicId, $managerUserPublicId, $headManager, $this->publicIds($ids));
    }

    /**
     * @return list<ManagerRelationshipSummary>
     */
    private function relationshipRows(bool $activeOnly, ?string $teamPublicId): array
    {
        $rows = [];

        foreach ($this->relationshipQuery()
            ->when($activeOnly, fn (Builder $query) => $this->currentlyValidWhere($query, 'relationships'))
            ->when($teamPublicId !== null, static fn (Builder $query) => $query->where('teams.public_id', $teamPublicId))
            ->orderBy('teams.name')
            ->orderBy('manager.name')
            ->orderBy('report.name')
            ->get() as $row) {
            $values = get_object_vars($row);
            $rows[] = new ManagerRelationshipSummary(
                publicId: $this->string($values['public_id'] ?? ''),
                teamPublicId: $this->string($values['team_public_id'] ?? ''),
                teamName: $this->displayName($values, 'team_display_name', 'team_name'),
                managerUserPublicId: $this->string($values['manager_public_id'] ?? ''),
                managerName: $this->string($values['manager_name'] ?? ''),
                managerEmail: $this->string($values['manager_email'] ?? ''),
                reportUserPublicId: $this->string($values['report_public_id'] ?? ''),
                reportName: $this->string($values['report_name'] ?? ''),
                reportEmail: $this->string($values['report_email'] ?? ''),
                validFrom: $this->string($values['valid_from'] ?? ''),
                validTo: $this->nullableString($values['valid_to'] ?? null),
                reason: $this->string($values['reason'] ?? ''),
                endReason: $this->nullableString($values['end_reason'] ?? null),
            );
        }

        return $rows;
    }

    private function relationshipQuery(): Builder
    {
        return DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' as relationships')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'relationships.team_id', '=', 'teams.id')
            ->join(IdentityDatabaseTable::USERS.' as manager', 'relationships.manager_user_id', '=', 'manager.id')
            ->join(IdentityDatabaseTable::USERS.' as report', 'relationships.report_user_id', '=', 'report.id')
            ->select([
                'relationships.public_id',
                'relationships.valid_from',
                'relationships.valid_to',
                'relationships.reason',
                'relationships.end_reason',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
                'manager.public_id as manager_public_id',
                'manager.name as manager_name',
                'manager.email as manager_email',
                'report.public_id as report_public_id',
                'report.name as report_name',
                'report.email as report_email',
            ]);
    }

    /**
     * @param  array<mixed>  $values
     */
    private function displayName(array $values, string $displayKey, string $fallbackKey): string
    {
        $displayName = $this->string($values[$displayKey] ?? '');

        return $displayName !== '' ? $displayName : $this->string($values[$fallbackKey] ?? '');
    }

    /**
     * @return array<int, array{public_id: string, name: string, email: string, head: bool}>
     */
    private function activeMembers(int $teamId): array
    {
        $members = [];

        foreach (DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments')
            ->join(IdentityDatabaseTable::USERS.' as users', 'assignments.user_id', '=', 'users.id')
            ->where('assignments.team_id', $teamId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query, 'assignments'))
            ->orderBy('users.name')
            ->get(['users.id', 'users.public_id', 'users.name', 'users.email', 'assignments.is_head_manager']) as $row) {
            $values = get_object_vars($row);
            $id = $values['id'] ?? null;

            if (is_int($id)) {
                $members[$id] = [
                    'public_id' => $this->string($values['public_id'] ?? ''),
                    'name' => $this->string($values['name'] ?? ''),
                    'email' => $this->string($values['email'] ?? ''),
                    'head' => (bool) ($values['is_head_manager'] ?? false),
                ];
            }
        }

        return $members;
    }

    /**
     * @return array<int, list<int>>
     */
    private function activeChildren(int $teamId): array
    {
        $children = [];

        foreach (DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->get(['manager_user_id', 'report_user_id']) as $row) {
            $values = get_object_vars($row);
            $managerId = $values['manager_user_id'] ?? null;
            $reportId = $values['report_user_id'] ?? null;

            if (is_int($managerId) && is_int($reportId)) {
                $children[$managerId] ??= [];
                $children[$managerId][] = $reportId;
            }
        }

        return $children;
    }

    /**
     * @param  array<int, array{public_id: string, name: string, email: string, head: bool}>  $members
     * @param  array<int, list<int>>  $children
     * @param  list<int>  $path
     */
    private function node(int $userId, array $members, array $children, array $path): ManagerHierarchyNode
    {
        $member = $members[$userId] ?? ['public_id' => '', 'name' => '', 'email' => '', 'head' => false];

        if (in_array($userId, $path, true)) {
            return new ManagerHierarchyNode($member['public_id'], $member['name'], $member['email'], $member['head'], []);
        }

        $reports = [];

        foreach ($children[$userId] ?? [] as $reportId) {
            $reports[] = $this->node($reportId, $members, $children, [...$path, $userId]);
        }

        return new ManagerHierarchyNode($member['public_id'], $member['name'], $member['email'], $member['head'], $reports);
    }

    private function wouldCreateCycle(int $teamId, int $managerUserId, int $reportUserId): bool
    {
        $children = $this->activeChildren($teamId);
        $children[$managerUserId] ??= [];
        $children[$managerUserId][] = $reportUserId;

        return $this->canReach($reportUserId, $managerUserId, $children, []);
    }

    /**
     * @param  array<int, list<int>>  $children
     * @param  list<int>  $visited
     */
    private function canReach(int $fromUserId, int $targetUserId, array $children, array $visited): bool
    {
        if ($fromUserId === $targetUserId) {
            return true;
        }

        if (in_array($fromUserId, $visited, true)) {
            return false;
        }

        foreach ($children[$fromUserId] ?? [] as $childId) {
            if ($this->canReach($childId, $targetUserId, $children, [...$visited, $fromUserId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(int $teamId, int $userId): array
    {
        $children = $this->activeChildren($teamId);
        $result = [];
        $stack = $children[$userId] ?? [];

        while ($stack !== []) {
            $current = array_shift($stack);

            if (in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            array_push($stack, ...($children[$current] ?? []));
        }

        return $result;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function publicIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            DB::table(IdentityDatabaseTable::USERS)->whereIn('id', $userIds)->orderBy('name')->pluck('public_id')->all(),
        ));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function resolveTeamUserIds(string $teamPublicId, string $managerUserPublicId, string $reportUserPublicId): array
    {
        return [
            $this->teamId($teamPublicId),
            $this->userId($managerUserPublicId),
            $this->userId($reportUserPublicId),
        ];
    }

    /**
     * @return array{team_id: int, manager_user_id: int, report_user_id: int, valid_to: ?string}|null
     */
    private function relationshipByPublicId(string $publicId): ?array
    {
        $row = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('public_id', $publicId)
            ->first(['team_id', 'manager_user_id', 'report_user_id', 'valid_to']);

        if (! is_object($row)) {
            return null;
        }

        $values = get_object_vars($row);
        $teamId = $values['team_id'] ?? null;
        $managerUserId = $values['manager_user_id'] ?? null;
        $reportUserId = $values['report_user_id'] ?? null;

        if (! is_int($teamId) || ! is_int($managerUserId) || ! is_int($reportUserId)) {
            return null;
        }

        return [
            'team_id' => $teamId,
            'manager_user_id' => $managerUserId,
            'report_user_id' => $reportUserId,
            'valid_to' => $this->nullableString($values['valid_to'] ?? null),
        ];
    }

    private function activeRelationshipExists(int $teamId, int $managerUserId, int $reportUserId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->where('manager_user_id', $managerUserId)
            ->where('report_user_id', $reportUserId)
            ->whereNull('valid_to')
            ->exists();
    }

    private function hasActiveMembership(int $teamId, int $userId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->exists();
    }

    private function currentlyValidWhere(Builder $query, ?string $alias = null): Builder
    {
        $validFrom = $alias === null ? 'valid_from' : $alias.'.valid_from';
        $validTo = $alias === null ? 'valid_to' : $alias.'.valid_to';

        return $query
            ->where(static function (Builder $nested) use ($validFrom): void {
                $nested->whereNull($validFrom)->orWhere($validFrom, '<=', now());
            })
            ->where(static function (Builder $nested) use ($validTo): void {
                $nested->whereNull($validTo)->orWhere($validTo, '>', now());
            });
    }

    private function teamId(string $teamPublicId): int
    {
        $id = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($id)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $id;
    }

    private function teamPublicId(int $teamId): string
    {
        $publicId = DB::table(TeamsDatabaseTable::TEAMS)->where('id', $teamId)->value('public_id');

        return is_string($publicId) ? $publicId : '';
    }

    private function userId(string $userPublicId): int
    {
        $id = DB::table(IdentityDatabaseTable::USERS)->where('public_id', $userPublicId)->value('id');

        if (! is_int($id)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        string $actorPublicId,
        string $teamPublicId,
        string $action,
        string $result,
        string $targetType,
        string $targetPublicId,
        array $before,
        array $after,
    ): void {
        $this->audit->record(new AuditEvent(
            module: 'teams',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: $actorPublicId,
            targetType: $targetType,
            targetPublicId: $targetPublicId,
            teamPublicId: $teamPublicId,
            before: $before,
            after: $after,
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
