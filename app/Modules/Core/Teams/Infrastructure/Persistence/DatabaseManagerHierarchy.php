<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Teams\Application\Exceptions\ManagerHierarchyViolation;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerHierarchyNode;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerImpactPreview;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerRelationshipSummary;
use App\Modules\Core\Teams\Application\Public\DTOs\ManagerScope;
use App\Modules\Core\Teams\Application\Public\DTOs\StructuralRoleChangePreview;
use App\Modules\Core\Teams\Domain\Enums\TeamStructuralRole;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

final class DatabaseManagerHierarchy implements ManagerHierarchy
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly UserLookup $users,
    ) {}

    public function version(string $teamPublicId): string
    {
        $teamId = $this->teamId($teamPublicId);
        $membershipVersion = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->get(['id', 'structural_role', 'valid_from', 'valid_to', 'updated_at'])
            ->map(static fn (object $row): array => get_object_vars($row))
            ->all();
        $relationshipVersion = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->get(['id', 'manager_user_id', 'report_user_id', 'valid_from', 'valid_to', 'updated_at'])
            ->map(static fn (object $row): array => get_object_vars($row))
            ->all();

        return hash('sha256', json_encode([$membershipVersion, $relationshipVersion], JSON_THROW_ON_ERROR));
    }

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

        if ($this->activeStructuralRole($teamId, $managerUserId) !== TeamStructuralRole::Manager) {
            $warnings[] = 'Only a structural Manager can own direct-report relationships.';
        }

        if ($this->activeStructuralRole($teamId, $reportUserId) === TeamStructuralRole::HeadManager) {
            $warnings[] = 'A Head Manager cannot participate in normal manager relationships.';
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
        ?string $expectedVersion = null,
    ): void {
        $this->assertVersion($teamPublicId, $expectedVersion);
        [$teamId, $managerUserId, $reportUserId] = $this->resolveTeamUserIds($teamPublicId, $managerUserPublicId, $reportUserPublicId);
        $actorUserId = $this->userId($actorUserPublicId);
        $validFromAt = Carbon::parse($validFrom);

        if ($managerUserId === $reportUserId) {
            throw ManagerHierarchyViolation::selfManagement();
        }

        if (! $this->hasActiveMembership($teamId, $managerUserId) || ! $this->hasActiveMembership($teamId, $reportUserId)) {
            throw ManagerHierarchyViolation::inactiveMembership();
        }

        if ($this->activeStructuralRole($teamId, $managerUserId) !== TeamStructuralRole::Manager) {
            throw ManagerHierarchyViolation::managerRoleRequired();
        }

        if ($this->activeStructuralRole($teamId, $reportUserId) === TeamStructuralRole::HeadManager) {
            throw ManagerHierarchyViolation::headManagerRelationshipForbidden();
        }

        if ($this->activeRelationshipExists($teamId, $managerUserId, $reportUserId)) {
            throw ManagerHierarchyViolation::duplicateActiveRelationship();
        }

        if ($this->wouldCreateCycle($teamId, $managerUserId, $reportUserId)) {
            throw ManagerHierarchyViolation::cycle();
        }

        $publicId = (string) Str::ulid();

        DB::transaction(function () use ($actorUserPublicId, $teamPublicId, $managerUserPublicId, $reportUserPublicId, $teamId, $managerUserId, $reportUserId, $actorUserId, $validFromAt, $reason, $publicId): void {
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

            $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.manager_relationship.created', 'succeeded', 'manager_relationship', $publicId, [], [
                'managerUserPublicId' => $managerUserPublicId,
                'reportUserPublicId' => $reportUserPublicId,
                'validFrom' => $validFromAt->toISOString(),
                'reason' => $reason,
            ]);
        });
    }

    public function end(string $actorUserPublicId, string $relationshipPublicId, string $validTo, string $reason, ?string $expectedVersion = null): void
    {
        $relationship = $this->relationshipByPublicId($relationshipPublicId);

        if ($relationship === null || $relationship['valid_to'] !== null) {
            throw ManagerHierarchyViolation::missingActiveRelationship();
        }

        $this->assertVersion($this->teamPublicId($relationship['team_id']), $expectedVersion);

        $actorUserId = $this->userId($actorUserPublicId);
        $validToAt = Carbon::parse($validTo);

        $teamPublicId = $this->teamPublicId($relationship['team_id']);
        DB::transaction(function () use ($actorUserPublicId, $teamPublicId, $relationshipPublicId, $actorUserId, $validToAt, $reason): void {
            DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
                ->where('public_id', $relationshipPublicId)
                ->whereNull('valid_to')
                ->update([
                    'valid_to' => $validToAt,
                    'ended_by_user_id' => $actorUserId,
                    'end_reason' => $reason,
                    'updated_at' => now(),
                ]);

            $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.manager_relationship.ended', 'succeeded', 'manager_relationship', $relationshipPublicId, [
                'validTo' => null,
            ], [
                'validTo' => $validToAt->toISOString(),
                'reason' => $reason,
            ]);
        });
    }

    public function previewStructuralRoleChange(string $teamPublicId, string $userPublicId, string $targetRole): StructuralRoleChangePreview
    {
        $teamId = $this->teamId($teamPublicId);
        $userId = $this->userId($userPublicId);
        $currentRole = $this->activeStructuralRole($teamId, $userId);
        $target = TeamStructuralRole::tryFrom($targetRole);
        $warnings = [];

        if ($currentRole === null) {
            $warnings[] = 'The user must have active access to the selected team.';
        }
        if ($target === null) {
            $warnings[] = 'Select a valid structural role.';
        }
        if ($currentRole !== null && $target === $currentRole) {
            $warnings[] = 'The selected structural role is already active.';
        }
        if ($currentRole === TeamStructuralRole::HeadManager
            && $target !== TeamStructuralRole::HeadManager
            && $this->activeHeadManagerCount($teamId) <= 1
        ) {
            $warnings[] = 'The last active head manager cannot be changed. Assign another head manager first.';
        }

        $relationships = $currentRole === null || $target === null
            ? []
            : $this->relationshipsEndedByRoleChange($teamId, $userId, $currentRole, $target);
        $relationshipPublicIds = array_map(fn (array $relationship): string => $relationship['public_id'], $relationships);
        $affectedUserIds = [];

        foreach ($relationships as $relationship) {
            $affectedUserIds[] = $relationship['manager_user_id'] === $userId
                ? $relationship['report_user_id']
                : $relationship['manager_user_id'];
        }

        return new StructuralRoleChangePreview(
            allowed: $warnings === [],
            currentRole: $currentRole->value ?? '',
            targetRole: $target->value ?? $targetRole,
            endingRelationshipPublicIds: $relationshipPublicIds,
            affectedUserPublicIds: $this->publicIds(array_values(array_unique($affectedUserIds))),
            warnings: $warnings,
        );
    }

    public function changeStructuralRole(
        string $actorUserPublicId,
        string $teamPublicId,
        string $userPublicId,
        string $targetRole,
        string $reason,
        ?string $expectedVersion = null,
    ): void {
        try {
            DB::transaction(function () use ($actorUserPublicId, $teamPublicId, $userPublicId, $targetRole, $reason, $expectedVersion): void {
                if (trim($reason) === '') {
                    throw ManagerHierarchyViolation::structuralRoleReasonRequired();
                }

                $target = TeamStructuralRole::tryFrom($targetRole);

                if ($target === null) {
                    throw ManagerHierarchyViolation::invalidStructuralRole();
                }

                $teamId = $this->teamId($teamPublicId);
                DB::table(TeamsDatabaseTable::TEAMS)->where('id', $teamId)->lockForUpdate()->first();
                $this->assertVersion($teamPublicId, $expectedVersion);
                $userId = $this->userId($userPublicId);
                $membership = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
                    ->where('team_id', $teamId)
                    ->where('user_id', $userId)
                    ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
                    ->lockForUpdate()
                    ->first(['id', 'structural_role']);

                if (! is_object($membership)) {
                    throw ManagerHierarchyViolation::inactiveMembership();
                }

                $membershipValues = get_object_vars($membership);
                $current = TeamStructuralRole::tryFrom($this->string($membershipValues['structural_role'] ?? ''));

                if ($current === null) {
                    throw ManagerHierarchyViolation::invalidStructuralRole();
                }
                if ($current === $target) {
                    throw ManagerHierarchyViolation::unchangedStructuralRole();
                }
                if ($current === TeamStructuralRole::HeadManager
                    && $target !== TeamStructuralRole::HeadManager
                    && $this->activeHeadManagerCount($teamId) <= 1
                ) {
                    throw ManagerHierarchyViolation::lastHeadManager();
                }

                $relationships = $this->relationshipsEndedByRoleChange($teamId, $userId, $current, $target, lock: true);
                $relationshipPublicIds = array_map(fn (array $relationship): string => $relationship['public_id'], $relationships);
                $actorUserId = $this->userId($actorUserPublicId);
                $endedAt = now();

                if ($relationshipPublicIds !== []) {
                    DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
                        ->whereIn('public_id', $relationshipPublicIds)
                        ->update([
                            'valid_to' => $endedAt,
                            'ended_by_user_id' => $actorUserId,
                            'end_reason' => $reason,
                            'updated_at' => $endedAt,
                        ]);
                }

                DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
                    ->where('id', $this->int($membershipValues['id'] ?? null))
                    ->update(['structural_role' => $target->value, 'updated_at' => $endedAt]);

                $this->recordAudit(
                    $actorUserPublicId,
                    $teamPublicId,
                    'team.structural_role.changed',
                    'succeeded',
                    'team_user_assignment',
                    $userPublicId,
                    ['structuralRole' => $current->value, 'activeRelationshipPublicIds' => $relationshipPublicIds],
                    ['structuralRole' => $target->value, 'endedRelationshipPublicIds' => $relationshipPublicIds, 'reason' => $reason],
                );
            });
        } catch (ManagerHierarchyViolation $exception) {
            $this->recordAudit($actorUserPublicId, $teamPublicId, 'team.structural_role.change_rejected', 'rejected', 'team_user_assignment', $userPublicId, [], [
                'targetRole' => $targetRole,
                'reason' => $reason,
                'violation' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function assertVersion(string $teamPublicId, ?string $expectedVersion): void
    {
        if ($expectedVersion !== null && ! hash_equals($this->version($teamPublicId), $expectedVersion)) {
            throw ManagerHierarchyViolation::staleStructure();
        }
    }

    public function scopeFor(string $teamPublicId, string $managerUserPublicId): ManagerScope
    {
        $teamId = $this->teamId($teamPublicId);
        $managerUserId = $this->userId($managerUserPublicId);
        $role = $this->activeStructuralRole($teamId, $managerUserId);
        $headManager = $role === TeamStructuralRole::HeadManager;
        $ids = $headManager
            ? array_keys($this->activeMembers($teamId))
            : ($role === TeamStructuralRole::Manager ? ($this->activeChildren($teamId)[$managerUserId] ?? []) : []);

        return new ManagerScope($teamPublicId, $managerUserPublicId, $headManager, $this->publicIds($ids));
    }

    /**
     * @return list<ManagerRelationshipSummary>
     */
    private function relationshipRows(bool $activeOnly, ?string $teamPublicId): array
    {
        $rows = [];

        $relationshipRows = $this->relationshipQuery()
            ->when($activeOnly, fn (Builder $query) => $this->currentlyValidWhere($query, 'relationships'))
            ->when($teamPublicId !== null, static fn (Builder $query) => $query->where('teams.public_id', $teamPublicId))
            ->orderBy('teams.name')
            ->get()
            ->all();
        $userSummaries = $this->users->displaySummariesForInternalIds($this->relationshipUserIds($relationshipRows));

        foreach ($relationshipRows as $row) {
            $values = get_object_vars($row);
            $managerUserId = $this->int($values['manager_user_id'] ?? null);
            $reportUserId = $this->int($values['report_user_id'] ?? null);
            $manager = $userSummaries[$managerUserId] ?? null;
            $report = $userSummaries[$reportUserId] ?? null;

            $rows[] = new ManagerRelationshipSummary(
                publicId: $this->string($values['public_id'] ?? ''),
                teamPublicId: $this->string($values['team_public_id'] ?? ''),
                teamName: $this->displayName($values, 'team_display_name', 'team_name'),
                managerUserPublicId: $manager === null ? '' : $manager->publicId,
                managerName: $manager === null ? '' : $manager->name,
                managerEmail: $manager === null ? '' : $manager->email,
                reportUserPublicId: $report === null ? '' : $report->publicId,
                reportName: $report === null ? '' : $report->name,
                reportEmail: $report === null ? '' : $report->email,
                validFrom: $this->string($values['valid_from'] ?? ''),
                validTo: $this->nullableString($values['valid_to'] ?? null),
                reason: $this->string($values['reason'] ?? ''),
                endReason: $this->nullableString($values['end_reason'] ?? null),
            );
        }

        usort($rows, static function (ManagerRelationshipSummary $first, ManagerRelationshipSummary $second): int {
            return [$first->teamName, $first->managerName, $first->reportName] <=> [$second->teamName, $second->managerName, $second->reportName];
        });

        return $rows;
    }

    private function relationshipQuery(): Builder
    {
        return DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS.' as relationships')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'relationships.team_id', '=', 'teams.id')
            ->select([
                'relationships.public_id',
                'relationships.manager_user_id',
                'relationships.report_user_id',
                'relationships.valid_from',
                'relationships.valid_to',
                'relationships.reason',
                'relationships.end_reason',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
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

        $assignmentRows = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments')
            ->where('assignments.team_id', $teamId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query, 'assignments'))
            ->get(['assignments.user_id', 'assignments.structural_role'])
            ->all();
        $userSummaries = $this->users->displaySummariesForInternalIds(array_values(array_map(
            fn (object $row): int => $this->int(get_object_vars($row)['user_id'] ?? null),
            $assignmentRows,
        )));

        foreach ($assignmentRows as $row) {
            $values = get_object_vars($row);
            $id = $values['user_id'] ?? null;

            if (is_numeric($id)) {
                $id = (int) $id;
                $user = $userSummaries[$id] ?? null;
                $members[$id] = [
                    'public_id' => $user === null ? '' : $user->publicId,
                    'name' => $user === null ? '' : $user->name,
                    'email' => $user === null ? '' : $user->email,
                    'head' => ($values['structural_role'] ?? null) === TeamStructuralRole::HeadManager->value,
                ];
            }
        }

        uasort($members, static fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

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

        $summaries = $this->users->displaySummariesForInternalIds($userIds);
        uasort($summaries, static fn ($first, $second): int => strcmp($first->name, $second->name));

        return array_values(array_map(static fn ($summary): string => $summary->publicId, $summaries));
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

    private function activeStructuralRole(int $teamId, int $userId): ?TeamStructuralRole
    {
        $role = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->value('structural_role');

        return is_string($role) ? TeamStructuralRole::tryFrom($role) : null;
    }

    private function activeHeadManagerCount(int $teamId): int
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('structural_role', TeamStructuralRole::HeadManager->value)
            ->where(fn (Builder $query) => $this->currentlyValidWhere($query))
            ->count();
    }

    /**
     * @return list<array{public_id: string, manager_user_id: int, report_user_id: int}>
     */
    private function relationshipsEndedByRoleChange(
        int $teamId,
        int $userId,
        TeamStructuralRole $currentRole,
        TeamStructuralRole $targetRole,
        bool $lock = false,
    ): array {
        $query = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->where(fn (Builder $builder) => $this->currentlyValidWhere($builder));

        if ($targetRole === TeamStructuralRole::HeadManager) {
            $query->where(static fn (Builder $builder) => $builder
                ->where('manager_user_id', $userId)
                ->orWhere('report_user_id', $userId));
        } elseif ($currentRole === TeamStructuralRole::Manager && $targetRole === TeamStructuralRole::Employee) {
            $query->where('manager_user_id', $userId);
        } else {
            return [];
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return array_values(array_map(function (object $row): array {
            $values = get_object_vars($row);

            return [
                'public_id' => $this->string($values['public_id'] ?? ''),
                'manager_user_id' => $this->int($values['manager_user_id'] ?? null),
                'report_user_id' => $this->int($values['report_user_id'] ?? null),
            ];
        }, $query->get(['public_id', 'manager_user_id', 'report_user_id'])->all()));
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
        $id = $this->users->internalIdForPublicId($userPublicId);

        if ($id === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $id;
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return list<int>
     */
    private function relationshipUserIds(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $ids[] = $this->int($values['manager_user_id'] ?? null);
            $ids[] = $this->int($values['report_user_id'] ?? null);
        }

        return array_values(array_filter(array_unique($ids), static fn (int $id): bool => $id > 0));
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
