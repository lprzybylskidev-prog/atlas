<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Admin;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationEligibilityChecker;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationSessionState;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Public\DTOs\ImpersonationEligibility;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Identity\Domain\AccountSensitivity;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ImpersonationManager implements ImpersonationEligibilityChecker, ImpersonationSessionState
{
    private const ADMINISTRATOR_ROLE_NAME = 'system.administrator';

    private const ADMIN_MODE_ENTER_PERMISSION = 'admin-mode.enter';

    private const IMPERSONATION_START_PERMISSION = 'impersonation.start';

    private const IMPERSONATION_SENSITIVE_OVERRIDE_PERMISSION = 'impersonation.sensitive.override';

    public const SESSION_ID = 'atlas_impersonation_session_id';

    public const ACTOR_PUBLIC_ID = 'atlas_impersonation_actor_public_id';

    public const ACTOR_TEAM_PUBLIC_ID = 'atlas_impersonation_actor_team_public_id';

    public const USER_PUBLIC_ID = 'atlas_impersonation_user_public_id';

    public const USER_NAME = 'atlas_impersonation_user_name';

    public const TEAM_PUBLIC_ID = 'atlas_impersonation_team_public_id';

    public const TEAM_NAME = 'atlas_impersonation_team_name';

    public const REASON = 'atlas_impersonation_reason';

    public const STARTED_AT = 'atlas_impersonation_started_at';

    public function __construct(
        private EffectivePermissionChecker $permissions,
        private AdministrativeSessionManager $adminMode,
        private ImpersonationSimulationStore $simulation,
        private AuditRecorder $audit,
    ) {}

    public function active(Request $request): bool
    {
        return $this->sessionId($request) !== null;
    }

    public function sessionId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->get(self::SESSION_ID);

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    public function eligibility(Request $request, string $actorPublicId, string $targetPublicId, ?string $teamPublicId = null): ImpersonationEligibility
    {
        $actorTeamPublicId = $request->session()->get('active_team_public_id');
        $actor = User::query()->where('public_id', $actorPublicId)->first();
        $target = User::query()->where('public_id', $targetPublicId)->first();

        if (! $actor instanceof User) {
            return new ImpersonationEligibility(false, blockedReason: 'actor_missing');
        }

        if (! $target instanceof User) {
            return new ImpersonationEligibility(false, blockedReason: 'target_missing');
        }

        if (! is_string($actorTeamPublicId)) {
            return new ImpersonationEligibility(false, blockedReason: 'actor_team_missing');
        }

        if (! $this->adminMode->active($request) || ! $this->can($actor, self::IMPERSONATION_START_PERMISSION, $actorTeamPublicId)) {
            return new ImpersonationEligibility(false, blockedReason: 'permission_missing');
        }

        $sensitivity = AccountSensitivity::tryFrom((string) ($target->account_sensitivity ?? 'normal')) ?? AccountSensitivity::Normal;

        if ((string) $actor->public_id === (string) $target->public_id) {
            return new ImpersonationEligibility(false, blockedReason: 'self');
        }

        if (! $target->isActive()) {
            return new ImpersonationEligibility(false, blockedReason: 'inactive');
        }

        if (! $sensitivity->human()) {
            return new ImpersonationEligibility(false, blockedReason: 'non_human_account');
        }

        if ($this->hasAdministratorLevelAccess((string) $target->public_id)) {
            return new ImpersonationEligibility(false, blockedReason: 'administrator');
        }

        if ($teamPublicId !== null && ! $this->targetBelongsToTeam((string) $target->public_id, $teamPublicId)) {
            return new ImpersonationEligibility(false, blockedReason: 'team_unavailable');
        }

        if ($teamPublicId === null && ! $this->targetHasAvailableTeam((string) $target->public_id)) {
            return new ImpersonationEligibility(false, blockedReason: 'team_unavailable');
        }

        if ($sensitivity === AccountSensitivity::Sensitive) {
            if (! $this->can($actor, self::IMPERSONATION_SENSITIVE_OVERRIDE_PERMISSION, $actorTeamPublicId)
                || ! $this->adminMode->highRiskFresh($request)
            ) {
                return new ImpersonationEligibility(false, true, 'sensitive_override_missing');
            }

            return new ImpersonationEligibility(true, true);
        }

        return new ImpersonationEligibility(true);
    }

    public function start(Request $request, User $actor, string $targetPublicId, string $teamPublicId, string $reason, bool $overrideSensitive): bool
    {
        $actorTeamPublicId = $request->session()->get('active_team_public_id');
        $target = User::query()->where('public_id', $targetPublicId)->first();

        if (! $target instanceof User || ! is_string($actorTeamPublicId) || trim($reason) === '') {
            $this->record($request, 'impersonation.start', 'rejected', (string) $actor->public_id, $targetPublicId, null, $reason);

            return false;
        }

        $eligibility = $this->eligibility($request, (string) $actor->public_id, $targetPublicId, $teamPublicId);

        if (! $eligibility->canStart) {
            $this->record($request, 'impersonation.start', 'rejected', (string) $actor->public_id, $targetPublicId, null, $reason);

            return false;
        }

        $sensitivity = AccountSensitivity::tryFrom((string) ($target->account_sensitivity ?? 'normal')) ?? AccountSensitivity::Normal;

        if ($sensitivity === AccountSensitivity::Sensitive) {
            if (! $overrideSensitive) {
                $this->record($request, 'impersonation.sensitive_override', 'rejected', (string) $actor->public_id, (string) $target->public_id, $teamPublicId, $reason);

                return false;
            }

            $this->record($request, 'impersonation.sensitive_override', 'succeeded', (string) $actor->public_id, (string) $target->public_id, $teamPublicId, $reason, [
                'high_risk_operation' => HighRiskAdministrativeOperation::ImpersonationSensitiveOverride->value,
            ]);
        }

        $team = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->first(['name', 'display_name']);
        $teamName = $this->teamDisplayName($team);
        $sessionId = (string) Str::ulid();

        $request->session()->put(self::SESSION_ID, $sessionId);
        $request->session()->put(self::ACTOR_PUBLIC_ID, (string) $actor->public_id);
        $request->session()->put(self::ACTOR_TEAM_PUBLIC_ID, $actorTeamPublicId);
        $request->session()->put(self::USER_PUBLIC_ID, (string) $target->public_id);
        $request->session()->put(self::USER_NAME, (string) $target->name);
        $request->session()->put(self::TEAM_PUBLIC_ID, $teamPublicId);
        $request->session()->put(self::TEAM_NAME, $teamName);
        $request->session()->put(self::REASON, $reason);
        $request->session()->put(self::STARTED_AT, now()->toIso8601String());
        $request->session()->put('active_team_public_id', $teamPublicId);

        $this->record($request, 'impersonation.start', 'succeeded', (string) $actor->public_id, (string) $target->public_id, $teamPublicId, $reason, [
            'impersonation_session_id' => $sessionId,
            'target_online' => count(app(UserSessionRegistry::class)->activeForUser((string) $target->public_id)) > 0,
        ]);

        return true;
    }

    public function stop(Request $request, string $result = 'succeeded', string $reason = 'manual'): void
    {
        if (! $this->active($request)) {
            return;
        }

        $session = $request->session();
        $sessionId = $session->get(self::SESSION_ID);
        $actorPublicId = $session->get(self::ACTOR_PUBLIC_ID);
        $targetPublicId = $session->get(self::USER_PUBLIC_ID);
        $teamPublicId = $session->get(self::TEAM_PUBLIC_ID);
        $actorTeamPublicId = $session->get(self::ACTOR_TEAM_PUBLIC_ID);

        if (is_string($sessionId)) {
            $this->simulation->deleteSession($sessionId);
        }

        $session->forget([
            self::SESSION_ID,
            self::ACTOR_PUBLIC_ID,
            self::ACTOR_TEAM_PUBLIC_ID,
            self::USER_PUBLIC_ID,
            self::USER_NAME,
            self::TEAM_PUBLIC_ID,
            self::TEAM_NAME,
            self::REASON,
            self::STARTED_AT,
        ]);

        if (is_string($actorTeamPublicId)) {
            $session->put('active_team_public_id', $actorTeamPublicId);
        }

        $this->audit->record(new AuditEvent(
            module: 'identity',
            action: 'impersonation.end',
            result: $result,
            source: 'ui',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            actualActorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            impersonatedUserPublicId: is_string($targetPublicId) ? $targetPublicId : null,
            impersonationSessionId: is_string($sessionId) ? $sessionId : null,
            targetType: 'user',
            targetPublicId: is_string($targetPublicId) ? $targetPublicId : null,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            reason: $reason,
            security: true,
            securityCategory: SecurityAuditCategory::Impersonation,
        ));
    }

    public function applyEffectiveUser(Request $request): void
    {
        if (! $this->active($request)) {
            return;
        }

        $targetPublicId = $request->session()->get(self::USER_PUBLIC_ID);
        $targetTeamPublicId = $request->session()->get(self::TEAM_PUBLIC_ID);
        $actorPublicId = $request->session()->get(self::ACTOR_PUBLIC_ID);
        $actorTeamPublicId = $request->session()->get(self::ACTOR_TEAM_PUBLIC_ID);

        if (! is_string($targetPublicId) || ! is_string($targetTeamPublicId) || ! is_string($actorPublicId) || ! is_string($actorTeamPublicId)) {
            $this->stop($request, reason: 'invalid_context');

            return;
        }

        $actor = User::query()->where('public_id', $actorPublicId)->first();
        $target = User::query()->where('public_id', $targetPublicId)->first();

        if (! $actor instanceof User
            || ! $target instanceof User
            || ! $actor->isActive()
            || ! $target->isActive()
            || ! $this->can($actor, self::IMPERSONATION_START_PERMISSION, $actorTeamPublicId)
            || ! $this->targetBelongsToTeam($targetPublicId, $targetTeamPublicId)
            || ! $this->adminMode->active($request)
        ) {
            $this->stop($request, reason: 'security_invalidation');

            return;
        }

        $request->session()->put('active_team_public_id', $targetTeamPublicId);
        Auth::guard('web')->setUser($target);
    }

    /**
     * @return array{active: bool, sessionId: string|null, actorPublicId: string|null, userPublicId: string|null, userName: string|null, teamPublicId: string|null, teamName: string|null, reason: string|null, startedAt: string|null}
     */
    public function sharedState(Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : null;
        $sessionId = $session?->get(self::SESSION_ID);
        $actorPublicId = $session?->get(self::ACTOR_PUBLIC_ID);
        $userPublicId = $session?->get(self::USER_PUBLIC_ID);
        $userName = $session?->get(self::USER_NAME);
        $teamPublicIdValue = $session?->get(self::TEAM_PUBLIC_ID);
        $sessionTeamName = $session?->get(self::TEAM_NAME);
        $reason = $session?->get(self::REASON);
        $startedAt = $session?->get(self::STARTED_AT);
        $active = is_string($sessionId);
        $teamPublicId = $active && is_string($teamPublicIdValue) ? $teamPublicIdValue : null;
        $sessionTeamName = $active && is_string($sessionTeamName) ? $sessionTeamName : null;
        $teamName = $teamPublicId === null ? $sessionTeamName : ($this->teamDisplayNameForPublicId($teamPublicId) ?: $sessionTeamName);

        return [
            'active' => $active,
            'sessionId' => $active ? $sessionId : null,
            'actorPublicId' => $active && is_string($actorPublicId) ? $actorPublicId : null,
            'userPublicId' => $active && is_string($userPublicId) ? $userPublicId : null,
            'userName' => $active && is_string($userName) ? $userName : null,
            'teamPublicId' => $teamPublicId,
            'teamName' => $teamName,
            'reason' => $active && is_string($reason) ? $reason : null,
            'startedAt' => $active && is_string($startedAt) ? $startedAt : null,
        ];
    }

    private function can(User $user, string $permission, string $teamPublicId): bool
    {
        return $this->permissions->check(new EffectivePermissionRequest(
            userPublicId: (string) $user->public_id,
            permission: $permission,
            teamPublicId: $teamPublicId,
        ))->allowed;
    }

    private function hasAdministratorLevelAccess(string $userPublicId): bool
    {
        $user = DB::table(IdentityDatabaseTable::USERS)->where('public_id', $userPublicId)->first(['id']);

        if ($user === null || ! property_exists($user, 'id') || ! is_int($user->id)) {
            return false;
        }

        $roleName = self::ADMINISTRATOR_ROLE_NAME;
        $modelType = config('auth.providers.users.model');
        $modelType = is_string($modelType) && $modelType !== '' ? $modelType : User::class;

        if (DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->join(AuthorizationDatabaseTable::ROLES, 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $modelType)
            ->where('roles.name', $roleName)
            ->exists()) {
            return true;
        }

        return DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->join(AuthorizationDatabaseTable::PERMISSIONS, 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $user->id)
            ->where('model_has_permissions.model_type', $modelType)
            ->where('permissions.name', self::ADMIN_MODE_ENTER_PERMISSION)
            ->exists();
    }

    private function targetBelongsToTeam(string $userPublicId, string $teamPublicId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(IdentityDatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.public_id', $teamPublicId)
            ->where('teams.is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->exists();
    }

    private function targetHasAvailableTeam(string $userPublicId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(IdentityDatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(TeamsDatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->exists();
    }

    private function teamDisplayName(mixed $team): string
    {
        if (! is_object($team)) {
            return '';
        }

        $displayName = $team->display_name ?? null;
        $name = $team->name ?? null;

        return is_string($displayName) && $displayName !== ''
            ? $displayName
            : (is_string($name) ? $name : '');
    }

    private function teamDisplayNameForPublicId(string $teamPublicId): string
    {
        return $this->teamDisplayName(DB::table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->first(['name', 'display_name']));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(Request $request, string $action, string $result, string $actorPublicId, ?string $targetPublicId, ?string $teamPublicId, ?string $reason, array $metadata = []): void
    {
        $impersonationSessionId = $request->hasSession() && is_string($request->session()->get(self::SESSION_ID))
            ? $request->session()->get(self::SESSION_ID)
            : null;

        if ($impersonationSessionId === null && is_string($metadata['impersonation_session_id'] ?? null)) {
            $impersonationSessionId = $metadata['impersonation_session_id'];
        }

        $this->audit->record(new AuditEvent(
            module: 'identity',
            action: $action,
            result: $result,
            source: 'ui',
            actorPublicId: $actorPublicId,
            actualActorPublicId: $actorPublicId,
            impersonatedUserPublicId: $targetPublicId,
            impersonationSessionId: $impersonationSessionId,
            targetType: 'user',
            targetPublicId: $targetPublicId,
            teamPublicId: $teamPublicId,
            reason: $reason,
            metadata: $metadata,
            security: true,
            securityCategory: SecurityAuditCategory::Impersonation,
        ));
    }
}
