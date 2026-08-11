<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationCleaner;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner;
use App\Shared\Application\Teams\DTOs\AdminTeamUserMembership;
use App\Shared\Application\Teams\DTOs\AdminUserTeamMembership;
use App\Shared\Application\Teams\DTOs\TeamDisplaySummary;
use App\Shared\Application\Teams\DTOs\TeamLookupSummary;
use App\Shared\Application\Teams\DTOs\TeamOption;
use App\Shared\Application\Teams\DTOs\TeamUserAssignmentLookupSummary;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class DatabaseUserTeamMembershipManager implements TeamLookup, UserTeamMembershipManager, UserTeamMembershipProvisioner
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly UserTeamAuthorizationCleaner $authorization,
        private readonly UserSessionRegistry $sessions,
        private readonly UserLookup $users,
    ) {}

    public function activeMembershipsForUser(string $userPublicId): array
    {
        $memberships = [];

        $userId = $this->users->internalIdForPublicId($userPublicId);

        if ($userId === null) {
            return [];
        }

        foreach (DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('team_user_assignments.user_id', $userId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->orderBy('teams.name')
            ->get([
                'teams.public_id',
                'teams.name',
                'teams.display_name',
                'teams.is_active',
                'team_user_assignments.valid_from',
                'team_user_assignments.valid_to',
            ]) as $row) {
            $values = get_object_vars($row);
            $memberships[] = new AdminUserTeamMembership(
                teamPublicId: $this->scalarString($values['public_id'] ?? ''),
                teamName: $this->displayName($values),
                teamActive: (bool) ($values['is_active'] ?? false),
                validFrom: $this->nullableString($values['valid_from'] ?? null),
                validTo: $this->nullableString($values['valid_to'] ?? null),
            );
        }

        return $memberships;
    }

    public function hasActiveMembership(string $userPublicId, string $teamPublicId): bool
    {
        $userId = $this->users->internalIdForPublicId($userPublicId);

        if ($userId === null) {
            return false;
        }

        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('team_user_assignments.user_id', $userId)
            ->where('teams.public_id', $teamPublicId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->exists();
    }

    public function teamExists(string $teamPublicId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->exists();
    }

    public function internalIdForPublicId(string $teamPublicId): ?int
    {
        $id = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function publicIdForInternalId(int $teamId): ?string
    {
        $publicId = DB::table(TeamsDatabaseTable::TEAMS)->where('id', $teamId)->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    public function activeInternalIdForPublicId(string $teamPublicId): ?int
    {
        $id = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->where('is_active', true)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function activePublicIdForInternalId(int $teamId): ?string
    {
        $publicId = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('id', $teamId)
            ->where('is_active', true)
            ->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    public function hasActiveHeadManager(string $teamPublicId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('teams.public_id', $teamPublicId)
            ->where('team_user_assignments.is_head_manager', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->exists();
    }

    public function activeAssignmentInternalIdForUserTeam(int $userId, int $teamId): ?int
    {
        $id = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function assignmentSummariesForInternalIds(array $assignmentIds): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        $summaries = [];

        $assignmentRows = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS.' as assignments')
            ->join(TeamsDatabaseTable::TEAMS.' as teams', 'assignments.team_id', '=', 'teams.id')
            ->whereIn('assignments.id', array_values(array_unique($assignmentIds)))
            ->get([
                'assignments.id as assignment_id',
                'assignments.user_id',
                'teams.id as team_id',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'teams.display_name as team_display_name',
            ])
            ->all();
        $userSummaries = $this->users->displaySummariesForInternalIds(array_values(array_map(
            fn (object $row): int => $this->intValue(get_object_vars($row)['user_id'] ?? null),
            $assignmentRows,
        )));

        foreach ($assignmentRows as $row) {
            $values = get_object_vars($row);
            $assignmentId = $this->intValue($values['assignment_id'] ?? null);
            $userId = $this->intValue($values['user_id'] ?? null);
            $teamId = $this->intValue($values['team_id'] ?? null);
            $user = $userSummaries[$userId] ?? null;

            if ($assignmentId < 1 || $userId < 1 || $teamId < 1 || $user === null) {
                continue;
            }

            $summaries[$assignmentId] = new TeamUserAssignmentLookupSummary(
                assignmentId: $assignmentId,
                userId: $userId,
                userPublicId: $user->publicId,
                userName: $user->name,
                userEmail: $user->email,
                teamId: $teamId,
                teamPublicId: $this->scalarString($values['team_public_id'] ?? ''),
                teamName: $this->displayName([
                    'name' => $values['team_name'] ?? '',
                    'display_name' => $values['team_display_name'] ?? '',
                ]),
            );
        }

        ksort($summaries);

        return $summaries;
    }

    public function allInternalIds(): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            DB::table(TeamsDatabaseTable::TEAMS)
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        ));
    }

    public function allSummaries(): array
    {
        $summaries = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'display_name', 'is_active']) as $row) {
            $summary = $this->lookupSummary($row);

            if ($summary !== null) {
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }

    public function summariesForInternalIds(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        $summaries = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)
            ->whereIn('id', array_values(array_unique($teamIds)))
            ->get(['id', 'public_id', 'name', 'display_name', 'is_active']) as $row) {
            $summary = $this->lookupSummary($row);

            if ($summary !== null) {
                $summaries[$summary->internalId] = $summary;
            }
        }

        ksort($summaries);

        return $summaries;
    }

    public function internalIdsForPublicIds(array $teamPublicIds): array
    {
        if ($teamPublicIds === []) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            DB::table(TeamsDatabaseTable::TEAMS)
                ->whereIn('public_id', $teamPublicIds)
                ->pluck('id')
                ->all(),
        ));
    }

    public function displaySummariesForPublicIds(array $teamPublicIds): array
    {
        if ($teamPublicIds === []) {
            return [];
        }

        $summaries = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)
            ->whereIn('public_id', array_values(array_unique($teamPublicIds)))
            ->get(['public_id', 'name', 'display_name'])
            ->all() as $row) {
            $values = get_object_vars($row);
            $publicId = $this->scalarString($values['public_id'] ?? '');

            if ($publicId === '') {
                continue;
            }

            $summaries[$publicId] = new TeamDisplaySummary(
                publicId: $publicId,
                name: $this->displayName($values),
            );
        }

        ksort($summaries);

        return $summaries;
    }

    public function activeMembershipsForTeam(string $teamPublicId): array
    {
        $memberships = [];

        $assignmentRows = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('teams.public_id', $teamPublicId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->get([
                'team_user_assignments.user_id',
                'team_user_assignments.is_head_manager',
                'team_user_assignments.valid_from',
                'team_user_assignments.valid_to',
            ])
            ->all();
        $userSummaries = $this->users->displaySummariesForInternalIds(array_values(array_map(
            fn (object $row): int => $this->intValue(get_object_vars($row)['user_id'] ?? null),
            $assignmentRows,
        )));

        foreach ($assignmentRows as $row) {
            $values = get_object_vars($row);
            $userId = $this->intValue($values['user_id'] ?? null);
            $user = $userSummaries[$userId] ?? null;

            if ($user === null) {
                continue;
            }

            $memberships[] = new AdminTeamUserMembership(
                userPublicId: $user->publicId,
                userName: $user->name,
                userEmail: $user->email,
                validFrom: $this->nullableString($values['valid_from'] ?? null),
                validTo: $this->nullableString($values['valid_to'] ?? null),
                headManager: (bool) ($values['is_head_manager'] ?? false),
            );
        }

        usort($memberships, static fn (AdminTeamUserMembership $first, AdminTeamUserMembership $second): int => strcmp($first->userName, $second->userName));

        return $memberships;
    }

    public function assignableUsersForTeam(string $teamPublicId): array
    {
        $activeUserIds = [];

        foreach (DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('teams.public_id', $teamPublicId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->pluck('team_user_assignments.user_id')
            ->all() as $userId) {
            if (is_int($userId)) {
                $activeUserIds[] = $userId;
            }
        }

        $users = [];

        $activePublicIds = [];

        foreach ($this->users->displaySummariesForInternalIds($activeUserIds) as $summary) {
            $activePublicIds[$summary->publicId] = true;
        }

        foreach ($this->users->allActiveDisplaySummaries() as $summary) {
            if (isset($activePublicIds[$summary->publicId])) {
                continue;
            }

            $users[] = [
                'value' => $summary->publicId,
                'label' => trim($summary->name.' · '.$summary->email),
            ];
        }

        return $users;
    }

    public function assignableTeamsForUser(string $userPublicId): array
    {
        $activeTeamIds = [];

        $userId = $this->users->internalIdForPublicId($userPublicId);

        if ($userId === null) {
            return [];
        }

        foreach (DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_user_assignments.user_id', $userId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->pluck('team_user_assignments.team_id')
            ->all() as $teamId) {
            if (is_int($teamId)) {
                $activeTeamIds[] = $teamId;
            }
        }

        $teams = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)
            ->where('is_active', true)
            ->when($activeTeamIds !== [], static function (Builder $query) use ($activeTeamIds): void {
                $query->whereNotIn('id', $activeTeamIds);
            })
            ->orderBy('name')
            ->get(['public_id', 'name', 'display_name']) as $row) {
            $values = get_object_vars($row);
            $teams[] = new TeamOption(
                publicId: $this->scalarString($values['public_id'] ?? ''),
                name: $this->displayName($values),
            );
        }

        return $teams;
    }

    public function ensureUserTeamMembership(string $userPublicId, string $teamPublicId): void
    {
        $userId = $this->users->internalIdForPublicId($userPublicId);
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if ($userId === null || ! is_int($teamId)) {
            return;
        }

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->updateOrInsert([
            'team_id' => $teamId,
            'user_id' => $userId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function activeTeamOptions(): array
    {
        $teams = [];

        foreach (DB::table(TeamsDatabaseTable::TEAMS)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['public_id', 'name', 'display_name']) as $row) {
            $values = get_object_vars($row);
            $teams[] = new TeamOption(
                publicId: $this->scalarString($values['public_id'] ?? ''),
                name: $this->displayName($values),
            );
        }

        return $teams;
    }

    public function addAccess(string $actorPublicId, string $userPublicId, string $teamPublicId): void
    {
        [$userId, $teamId] = $this->resolveIds($userPublicId, $teamPublicId);
        $before = $this->membershipSnapshot($userId, $teamId);

        DB::transaction(function () use ($userId, $teamId): void {
            DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->updateOrInsert([
                'team_id' => $teamId,
                'user_id' => $userId,
            ], [
                'valid_from' => now(),
                'valid_to' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });

        $this->recordAudit($actorPublicId, $userPublicId, $teamPublicId, 'team.user_access_added', 'succeeded', $before, [
            'active' => true,
        ]);
    }

    public function removeAccess(string $actorPublicId, string $userPublicId, string $teamPublicId, string $reason): void
    {
        [$userId, $teamId] = $this->resolveIds($userPublicId, $teamPublicId);
        $before = $this->membershipSnapshot($userId, $teamId);

        if (($before['active'] ?? false) !== true) {
            $this->recordAudit($actorPublicId, $userPublicId, $teamPublicId, 'team.user_access_remove_rejected', 'rejected', $before, [
                'reason' => 'not_active',
            ]);

            return;
        }

        $isHeadManager = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where('is_head_manager', true)
            ->exists();
        $activeRelationshipExists = DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->where(static fn (Builder $query) => $query->where('manager_user_id', $userId)->orWhere('report_user_id', $userId))
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->exists();

        if ($isHeadManager || $activeRelationshipExists) {
            $this->recordAudit($actorPublicId, $userPublicId, $teamPublicId, 'team.user_access_remove_rejected', 'rejected', $before, [
                'reason' => 'active_team_structure',
            ]);

            throw ValidationException::withMessages([
                'reason' => __('validation.custom.team_assignments.active_structure'),
            ]);
        }

        DB::transaction(function () use ($userId, $teamId, $userPublicId, $teamPublicId): void {
            DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
                ->where('team_id', $teamId)
                ->where('user_id', $userId)
                ->update([
                    'valid_to' => now(),
                    'updated_at' => now(),
                ]);

            $this->authorization->removeAssignmentsForUserTeam($userPublicId, $teamPublicId);
        });

        $this->sessions->invalidateUserTeam($userPublicId, $teamPublicId);

        $this->recordAudit($actorPublicId, $userPublicId, $teamPublicId, 'team.user_access_removed', 'succeeded', $before, [
            'active' => false,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveIds(string $userPublicId, string $teamPublicId): array
    {
        $userId = $this->users->internalIdForPublicId($userPublicId);
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if ($userId === null || ! is_int($teamId)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return [$userId, $teamId];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSnapshot(int $userId, int $teamId): array
    {
        $row = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first(['valid_from', 'valid_to']);

        if (! is_object($row)) {
            return ['active' => false];
        }

        $values = get_object_vars($row);
        $validFrom = $values['valid_from'] ?? null;
        $validTo = $values['valid_to'] ?? null;
        $now = Carbon::now();
        $validFromString = $this->nullableString($validFrom);
        $validToString = $this->nullableString($validTo);
        $active = ($validFromString === null || Carbon::parse($validFromString)->lte($now))
            && ($validToString === null || Carbon::parse($validToString)->gt($now));

        return [
            'active' => $active,
            'valid_from' => $validFromString,
            'valid_to' => $validToString,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<mixed>  $values
     */
    private function displayName(array $values): string
    {
        $displayName = $this->scalarString($values['display_name'] ?? '');

        return $displayName !== '' ? $displayName : $this->scalarString($values['name'] ?? '');
    }

    private function lookupSummary(object $row): ?TeamLookupSummary
    {
        $values = get_object_vars($row);
        $id = $values['id'] ?? null;
        $publicId = $this->scalarString($values['public_id'] ?? '');

        if (! is_numeric($id) || $publicId === '') {
            return null;
        }

        return new TeamLookupSummary(
            internalId: (int) $id,
            publicId: $publicId,
            name: $this->displayName($values),
            active: (bool) ($values['is_active'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        string $actorPublicId,
        string $userPublicId,
        string $teamPublicId,
        string $action,
        string $result,
        array $before,
        array $after,
    ): void {
        $this->audit->record(new AuditEvent(
            module: 'teams',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: $actorPublicId,
            targetType: 'user',
            targetPublicId: $userPublicId,
            teamPublicId: $teamPublicId,
            before: $before,
            after: $after,
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }
}
