<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationCleaner;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Application\Public\DTOs\AdminTeamUserMembership;
use App\Modules\Core\Teams\Application\Public\DTOs\AdminUserTeamMembership;
use App\Modules\Core\Teams\Application\Public\DTOs\TeamOption;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class DatabaseUserTeamMembershipManager implements UserTeamMembershipManager
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly UserTeamAuthorizationCleaner $authorization,
        private readonly UserSessionRegistry $sessions,
    ) {}

    public function activeMembershipsForUser(string $userPublicId): array
    {
        $memberships = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
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
        return DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
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
        return DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->exists();
    }

    public function activeMembershipsForTeam(string $teamPublicId): array
    {
        $memberships = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('teams.public_id', $teamPublicId)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->orderBy('users.name')
            ->get([
                'users.public_id',
                'users.name',
                'users.email',
                'team_user_assignments.valid_from',
                'team_user_assignments.valid_to',
            ]) as $row) {
            $values = get_object_vars($row);
            $memberships[] = new AdminTeamUserMembership(
                userPublicId: $this->scalarString($values['public_id'] ?? ''),
                userName: $this->scalarString($values['name'] ?? ''),
                userEmail: $this->scalarString($values['email'] ?? ''),
                validFrom: $this->nullableString($values['valid_from'] ?? null),
                validTo: $this->nullableString($values['valid_to'] ?? null),
            );
        }

        return $memberships;
    }

    public function assignableUsersForTeam(string $teamPublicId): array
    {
        $activeUserIds = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
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

        foreach (DB::table(DatabaseTable::USERS)
            ->where('is_active', true)
            ->when($activeUserIds !== [], static function (Builder $query) use ($activeUserIds): void {
                $query->whereNotIn('id', $activeUserIds);
            })
            ->orderBy('name')
            ->get(['public_id', 'name', 'email']) as $row) {
            $values = get_object_vars($row);
            $name = $this->scalarString($values['name'] ?? '');
            $email = $this->scalarString($values['email'] ?? '');
            $users[] = [
                'value' => $this->scalarString($values['public_id'] ?? ''),
                'label' => trim($name.' · '.$email),
            ];
        }

        return $users;
    }

    public function assignableTeamsForUser(string $userPublicId): array
    {
        $activeTeamIds = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->where('users.public_id', $userPublicId)
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

        foreach (DB::table(DatabaseTable::TEAMS)
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

    public function activeTeamOptions(): array
    {
        $teams = [];

        foreach (DB::table(DatabaseTable::TEAMS)
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
            DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->updateOrInsert([
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

        DB::transaction(function () use ($userId, $teamId, $userPublicId, $teamPublicId): void {
            DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
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
        $userId = DB::table(DatabaseTable::USERS)->where('public_id', $userPublicId)->value('id');
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($userId) || ! is_int($teamId)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return [$userId, $teamId];
    }

    /**
     * @return array<string, mixed>
     */
    private function membershipSnapshot(int $userId, int $teamId): array
    {
        $row = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
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

    /**
     * @param  array<mixed>  $values
     */
    private function displayName(array $values): string
    {
        $displayName = $this->scalarString($values['display_name'] ?? '');

        return $displayName !== '' ? $displayName : $this->scalarString($values['name'] ?? '');
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
