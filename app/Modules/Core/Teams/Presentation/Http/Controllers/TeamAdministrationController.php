<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Settings\Application\Public\Contracts\SecuritySessionSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamSessionLimitSettings;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationException;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class TeamAdministrationController
{
    public function __construct(
        private readonly ArrayTableProcessor $tables,
        private readonly TableSavedViewService $views,
        private readonly TableRequestContext $context,
        private readonly AuditRecorder $audit,
        private readonly UserTeamMembershipManager $memberships,
        private readonly UserTeamAuthorizationManager $authorization,
        private readonly ModuleRegistry $modules,
        private readonly ModuleActivationService $activation,
        private readonly UserCredentialAccountDirectory $accounts,
        private readonly SecuritySessionSettings $securitySessionSettings,
        private readonly UserTeamSessionLimitSettings $sessionLimits,
        private readonly UserBreakPolicySettings $breakPolicies,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::TEAMS);
        $state = TableState::fromRequest($request, $definition);
        $filters = $this->filters($request);
        [$userId, $teamId] = $this->context->userTeam($request);
        $rows = array_values(Team::query()
            ->from(DatabaseTable::TEAMS.' as teams')
            ->select([
                'teams.id',
                'teams.public_id',
                'teams.name',
                'teams.display_name',
                'teams.is_active',
                'teams.created_at',
                'teams.updated_at',
            ])
            ->selectSub(
                DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
                    ->selectRaw('count(*)')
                    ->whereColumn('team_user_assignments.team_id', 'teams.id')
                    ->where(static function (Builder $query): void {
                        $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
                    })
                    ->where(static function (Builder $query): void {
                        $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
                    }),
                'members_count',
            )
            ->get()
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'publicId' => (string) $team->public_id,
                'name' => $team->name,
                'displayName' => is_string($team->display_name) && $team->display_name !== '' ? $team->display_name : $team->name,
                'isActive' => $team->is_active,
                'membersCount' => self::intValue($team->getAttribute('members_count')),
                'createdAt' => $team->created_at?->toISOString() ?? '',
                'updatedAt' => $team->updated_at?->toISOString() ?? '',
            ])
            ->all());
        $result = $this->tables->process($this->filteredRows($rows, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $result->rows,
            'table' => $table,
        ]);
    }

    /**
     * @return array{status: string, members: string}
     */
    private function filters(Request $request): array
    {
        $status = $request->query('status');
        $members = $request->query('members');

        return [
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'all',
            'members' => in_array($members, ['with', 'without'], true) ? $members : 'all',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{status: string, members: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'active' && ($row['isActive'] ?? false) !== true) {
                return false;
            }

            if ($filters['status'] === 'inactive' && ($row['isActive'] ?? false) === true) {
                return false;
            }

            if ($filters['members'] === 'with' && self::intValue($row['membersCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['members'] === 'without' && self::intValue($row['membersCount'] ?? 0) > 0) {
                return false;
            }

            return true;
        }));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Teams/Create', [
            'userOptions' => $this->userOptions(),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'rolePermissionMap' => $this->authorization->rolePermissionMap(),
            'moduleOptions' => $this->moduleOptions(),
            'sessionDefaults' => [
                'inactivityTimeoutMinutes' => $this->securitySessionSettings->inactivityTimeoutMinutes(),
                'sessionMaxLifetimeMinutes' => $this->globalSessionMaxLifetimeMinutes(),
            ],
            'breakDefaults' => $this->globalBreakDefaults(),
        ]);
    }

    public function edit(string $team): Response
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $teamBreakLimits = $this->breakPolicies->resolvedForTeam((string) $record->public_id);

        return Inertia::render('Admin/Teams/Edit', [
            'team' => [
                'publicId' => (string) $record->public_id,
                'name' => $record->name,
                'displayName' => is_string($record->display_name) && $record->display_name !== '' ? $record->display_name : $record->name,
                'isActive' => $record->is_active,
                'inactivityTimeoutMinutes' => $record->inactivity_timeout_minutes,
                'sessionMaxLifetimeMinutes' => $record->session_max_lifetime_minutes,
                'breakDailyLimitMinutes' => $teamBreakLimits['source'] === 'team' ? $teamBreakLimits['dailyLimitMinutes'] : null,
                'breakMaximumSingleMinutes' => $teamBreakLimits['source'] === 'team' ? $teamBreakLimits['maximumSingleBreakMinutes'] : null,
            ],
            'memberships' => array_map(function ($membership) use ($record): array {
                $assignments = $this->authorization->assignmentsForUserTeam($membership->userPublicId, (string) $record->public_id);
                $sessionLimits = $this->sessionLimits->resolvedForUserTeam($membership->userPublicId, (string) $record->public_id);
                $breakLimits = $this->breakPolicies->resolvedForUserTeam($membership->userPublicId, (string) $record->public_id);
                $hasUserTeamSessionOverride = $sessionLimits['source'] === 'user_team';
                $hasUserTeamBreakOverride = $breakLimits['source'] === 'user_team';

                return [
                    'userPublicId' => $membership->userPublicId,
                    'userName' => $membership->userName,
                    'userEmail' => $membership->userEmail,
                    'validFrom' => $membership->validFrom,
                    'validTo' => $membership->validTo,
                    'roleNames' => $assignments->roleNames,
                    'directPermissionNames' => $assignments->directPermissionNames,
                    'inactivityTimeoutMinutes' => $hasUserTeamSessionOverride ? $sessionLimits['inactivityTimeoutMinutes'] : null,
                    'sessionMaxLifetimeMinutes' => $hasUserTeamSessionOverride ? $sessionLimits['sessionMaxLifetimeMinutes'] : null,
                    'breakDailyLimitMinutes' => $hasUserTeamBreakOverride ? $breakLimits['dailyLimitMinutes'] : null,
                    'breakMaximumSingleMinutes' => $hasUserTeamBreakOverride ? $breakLimits['maximumSingleBreakMinutes'] : null,
                ];
            }, $this->memberships->activeMembershipsForTeam((string) $record->public_id)),
            'assignableUsers' => $this->memberships->assignableUsersForTeam((string) $record->public_id),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'rolePermissionMap' => $this->authorization->rolePermissionMap(),
            'moduleStates' => $this->teamModuleStates($record->id),
            'sessionDefaults' => [
                'inactivityTimeoutMinutes' => $this->securitySessionSettings->inactivityTimeoutMinutes(),
                'sessionMaxLifetimeMinutes' => $this->globalSessionMaxLifetimeMinutes(),
            ],
            'breakDefaults' => $this->globalBreakDefaults(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Team::class, 'name')],
            'display_name' => ['required', 'string', 'max:255'],
            'inactivity_timeout_minutes' => ['nullable', 'integer', 'min:1'],
            'session_max_lifetime_minutes' => ['nullable', 'integer', 'min:1'],
            'break_daily_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'break_maximum_single_minutes' => ['nullable', 'integer', 'min:1'],
            'user_assignments' => ['array'],
            'user_assignments.*.user_public_id' => ['nullable', 'string'],
            'user_assignments.*.role_names' => ['array'],
            'user_assignments.*.role_names.*' => ['string'],
            'user_assignments.*.direct_permission_names' => ['array'],
            'user_assignments.*.direct_permission_names.*' => ['string'],
            'user_assignments.*.inactivity_timeout_minutes' => ['nullable', 'integer', 'min:1'],
            'user_assignments.*.session_max_lifetime_minutes' => ['nullable', 'integer', 'min:1'],
            'user_assignments.*.break_daily_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'user_assignments.*.break_maximum_single_minutes' => ['nullable', 'integer', 'min:1'],
            'module_overrides' => ['array'],
            'module_overrides.*.module_key' => ['required', 'string'],
            'module_overrides.*.enabled' => ['required', 'boolean'],
            'module_overrides.*.reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $userAssignments = $this->userAssignments($validated);
        $teamInactivityTimeoutMinutes = $this->nullableIntValue($validated, 'inactivity_timeout_minutes');
        $teamSessionMaxLifetimeMinutes = $this->nullableIntValue($validated, 'session_max_lifetime_minutes');
        $this->validateSessionLimits($teamInactivityTimeoutMinutes, $teamSessionMaxLifetimeMinutes);

        $this->validateUserAssignments(
            $userAssignments,
            $teamInactivityTimeoutMinutes ?? $this->securitySessionSettings->inactivityTimeoutMinutes(),
            $teamSessionMaxLifetimeMinutes ?? $this->globalSessionMaxLifetimeMinutes(),
        );

        $record = Team::query()->create([
            'name' => is_string($validated['name'] ?? null) ? $validated['name'] : '',
            'display_name' => is_string($validated['display_name'] ?? null) ? $validated['display_name'] : '',
            'inactivity_timeout_minutes' => $teamInactivityTimeoutMinutes,
            'session_max_lifetime_minutes' => $teamSessionMaxLifetimeMinutes,
        ]);
        $this->breakPolicies->setTeamOverrides(
            (string) $record->public_id,
            $this->nullableIntValue($validated, 'break_daily_limit_minutes'),
            $this->nullableIntValue($validated, 'break_maximum_single_minutes'),
        );

        $this->recordAudit($request, 'team.created', 'succeeded', 'team', (string) $record->public_id, [], [
            'name' => $record->name,
            'display_name' => $record->display_name,
            'isActive' => $record->is_active,
        ]);

        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            foreach ($userAssignments as $assignment) {
                $this->memberships->addAccess($actorPublicId, $assignment['user_public_id'], (string) $record->public_id);
                $this->authorization->replaceAssignmentsForUserTeam(
                    actorPublicId: $actorPublicId,
                    userPublicId: $assignment['user_public_id'],
                    teamPublicId: (string) $record->public_id,
                    roleNames: $assignment['role_names'],
                    directPermissionNames: $assignment['direct_permission_names'],
                    reason: 'Initial team member assignment.',
                );
                $this->sessionLimits->setUserTeamOverrides(
                    $assignment['user_public_id'],
                    (string) $record->public_id,
                    $assignment['inactivity_timeout_minutes'],
                    $assignment['session_max_lifetime_minutes'],
                );
                $this->breakPolicies->setUserTeamOverrides(
                    $assignment['user_public_id'],
                    (string) $record->public_id,
                    $assignment['break_daily_limit_minutes'],
                    $assignment['break_maximum_single_minutes'],
                );
            }

            foreach ($this->moduleOverrides($validated) as $override) {
                try {
                    $this->activation->change(new ModuleActivationChange(
                        moduleKey: $override['module_key'],
                        scope: ModuleActivationScope::Team,
                        enabled: $override['enabled'],
                        reason: $override['reason'],
                        actorUserId: $this->actorUserId($request),
                        teamId: $record->id,
                    ));
                } catch (ModuleActivationException) {
                    continue;
                }
            }
        }

        return redirect()->route('admin.teams.index')->with('flash.messages', [
            FlashMessage::success('flash.teams.created'),
        ]);
    }

    public function update(Request $request, string $team): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Team::class, 'name')->ignore($record->id),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'inactivity_timeout_minutes' => ['nullable', 'integer', 'min:1'],
            'session_max_lifetime_minutes' => ['nullable', 'integer', 'min:1'],
            'break_daily_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'break_maximum_single_minutes' => ['nullable', 'integer', 'min:1'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $teamLimits = $this->sessionLimits->resolvedForTeam((string) $record->public_id);
        $this->validateSessionLimits(
            $this->nullableIntValue($validated, 'inactivity_timeout_minutes'),
            $this->nullableIntValue($validated, 'session_max_lifetime_minutes'),
            $teamLimits['inactivityTimeoutMinutes'],
            $teamLimits['sessionMaxLifetimeMinutes'],
        );

        $before = [
            'name' => $record->name,
            'display_name' => $record->display_name,
            'isActive' => $record->is_active,
        ];
        $record->forceFill([
            'name' => is_string($validated['name'] ?? null) ? $validated['name'] : '',
            'display_name' => is_string($validated['display_name'] ?? null) ? $validated['display_name'] : '',
            'inactivity_timeout_minutes' => $this->nullableIntValue($validated, 'inactivity_timeout_minutes'),
            'session_max_lifetime_minutes' => $this->nullableIntValue($validated, 'session_max_lifetime_minutes'),
        ])->save();
        $this->breakPolicies->setTeamOverrides(
            (string) $record->public_id,
            $this->nullableIntValue($validated, 'break_daily_limit_minutes'),
            $this->nullableIntValue($validated, 'break_maximum_single_minutes'),
        );

        $this->recordAudit($request, 'team.updated', 'succeeded', 'team', (string) $record->public_id, $before, [
            'name' => $record->name,
            'display_name' => $record->display_name,
            'isActive' => $record->is_active,
        ]);

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.updated'),
        ]);
    }

    public function activate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, true);

        return redirect()->route('admin.teams.index')->with('flash.messages', [
            FlashMessage::success('flash.teams.activated'),
        ]);
    }

    public function deactivate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, false);

        return redirect()->route('admin.teams.index')->with('flash.messages', [
            FlashMessage::success('flash.teams.deactivated'),
        ]);
    }

    public function destroy(Request $request, string $team): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        $deleted = false;

        if ($record instanceof Team && ! DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->where('team_id', $record->id)->exists()) {
            $before = [
                'name' => $record->name,
                'isActive' => $record->is_active,
            ];
            $record->delete();
            $deleted = true;

            $this->recordAudit($request, 'team.deleted', 'succeeded', 'team', $team, $before, []);
        }

        if (! $deleted) {
            $this->recordAudit($request, 'team.delete_rejected', 'rejected', 'team', $team, [], []);
        }

        return redirect()->route('admin.teams.index')->with('flash.messages', [
            FlashMessage::success('flash.teams.delete_attempted'),
        ]);
    }

    public function addUser(Request $request, string $team): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $validated = $request->validate([
            'user_public_id' => ['required', 'string'],
        ]);

        $userPublicId = is_array($validated) && is_string($validated['user_public_id'] ?? null) ? $validated['user_public_id'] : '';

        if (! $this->accounts->publicIdExists($userPublicId)) {
            throw ValidationException::withMessages([
                'user_public_id' => __('validation.exists', ['attribute' => __('validation.attributes.user')]),
            ]);
        }

        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            $this->memberships->addAccess($actorPublicId, $userPublicId, (string) $record->public_id);
        }

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.access_added'),
        ]);
    }

    public function removeUser(Request $request, string $team, string $user): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = is_array($validated) && is_string($validated['reason'] ?? null) ? $validated['reason'] : '';
        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            $this->memberships->removeAccess($actorPublicId, $user, (string) $record->public_id, $reason);
        }

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.access_removed'),
        ]);
    }

    public function updateUserAuthorization(Request $request, string $team, string $user): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $validated = $request->validate([
            'role_names' => ['array'],
            'role_names.*' => ['string'],
            'direct_permission_names' => ['array'],
            'direct_permission_names.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
            'inactivity_timeout_minutes' => ['nullable', 'integer', 'min:1'],
            'session_max_lifetime_minutes' => ['nullable', 'integer', 'min:1'],
            'break_daily_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'break_maximum_single_minutes' => ['nullable', 'integer', 'min:1'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $teamLimits = $this->sessionLimits->resolvedForTeam((string) $record->public_id);
        $this->validateSessionLimits(
            $this->nullableIntValue($validated, 'inactivity_timeout_minutes'),
            $this->nullableIntValue($validated, 'session_max_lifetime_minutes'),
            $teamLimits['inactivityTimeoutMinutes'],
            $teamLimits['sessionMaxLifetimeMinutes'],
        );
        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            $this->authorization->replaceAssignmentsForUserTeam(
                actorPublicId: $actorPublicId,
                userPublicId: $user,
                teamPublicId: (string) $record->public_id,
                roleNames: $this->stringList($validated['role_names'] ?? []),
                directPermissionNames: $this->stringList($validated['direct_permission_names'] ?? []),
                reason: is_string($validated['reason'] ?? null) ? $validated['reason'] : null,
            );
            $this->sessionLimits->setUserTeamOverrides(
                $user,
                (string) $record->public_id,
                $this->nullableIntValue($validated, 'inactivity_timeout_minutes'),
                $this->nullableIntValue($validated, 'session_max_lifetime_minutes'),
            );
            $this->breakPolicies->setUserTeamOverrides(
                $user,
                (string) $record->public_id,
                $this->nullableIntValue($validated, 'break_daily_limit_minutes'),
                $this->nullableIntValue($validated, 'break_maximum_single_minutes'),
            );
        }

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('flash.messages', [
            FlashMessage::success('flash.teams.authorization_updated'),
        ]);
    }

    private function changeActivation(Request $request, string $team, bool $active): void
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $before = [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ];

        $record->forceFill(['is_active' => $active])->save();

        $this->recordAudit($request, $active ? 'team.activated' : 'team.deactivated', 'succeeded', 'team', $team, $before, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function userOptions(): array
    {
        $users = [];

        foreach ($this->accounts->allOptions() as $user) {
            $users[] = [
                'value' => $user->publicId,
                'label' => trim($user->name.' · '.$user->email),
            ];
        }

        return $users;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<array{user_public_id: string, role_names: list<string>, direct_permission_names: list<string>, inactivity_timeout_minutes: ?int, session_max_lifetime_minutes: ?int, break_daily_limit_minutes: ?int, break_maximum_single_minutes: ?int}>
     */
    private function userAssignments(array $values): array
    {
        $assignments = $values['user_assignments'] ?? [];

        if (! is_array($assignments)) {
            return [];
        }

        $result = [];

        foreach ($assignments as $assignment) {
            if (! is_array($assignment)) {
                continue;
            }

            $userPublicId = $assignment['user_public_id'] ?? '';

            if (! is_string($userPublicId) || $userPublicId === '') {
                continue;
            }

            $result[] = [
                'user_public_id' => $userPublicId,
                'role_names' => $this->stringList($assignment['role_names'] ?? []),
                'direct_permission_names' => $this->stringList($assignment['direct_permission_names'] ?? []),
                'inactivity_timeout_minutes' => $this->nullableIntValue($assignment, 'inactivity_timeout_minutes'),
                'session_max_lifetime_minutes' => $this->nullableIntValue($assignment, 'session_max_lifetime_minutes'),
                'break_daily_limit_minutes' => $this->nullableIntValue($assignment, 'break_daily_limit_minutes'),
                'break_maximum_single_minutes' => $this->nullableIntValue($assignment, 'break_maximum_single_minutes'),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{user_public_id: string, role_names: list<string>, direct_permission_names: list<string>, inactivity_timeout_minutes: ?int, session_max_lifetime_minutes: ?int, break_daily_limit_minutes: ?int, break_maximum_single_minutes: ?int}>  $assignments
     */
    private function validateUserAssignments(array $assignments, int $teamInactivityTimeoutMinutes, int $teamSessionMaxLifetimeMinutes): void
    {
        foreach ($assignments as $assignment) {
            if ($this->accounts->publicIdExists($assignment['user_public_id'])) {
                $this->validateSessionLimits(
                    $assignment['inactivity_timeout_minutes'],
                    $assignment['session_max_lifetime_minutes'],
                    $teamInactivityTimeoutMinutes,
                    $teamSessionMaxLifetimeMinutes,
                );

                continue;
            }

            throw ValidationException::withMessages([
                'user_assignments' => __('validation.exists', ['attribute' => 'user']),
            ]);
        }
    }

    /**
     * @param  array<mixed>  $values
     * @return list<array{module_key: string, enabled: bool, reason: string}>
     */
    private function moduleOverrides(array $values): array
    {
        $overrides = $values['module_overrides'] ?? [];

        if (! is_array($overrides)) {
            return [];
        }

        $result = [];

        foreach ($overrides as $override) {
            if (! is_array($override)) {
                continue;
            }

            $moduleKey = $override['module_key'] ?? '';
            $reason = $override['reason'] ?? '';

            if (! is_string($moduleKey) || $moduleKey === '' || ! is_string($reason) || trim($reason) === '') {
                continue;
            }

            $result[] = [
                'module_key' => $moduleKey,
                'enabled' => (bool) ($override['enabled'] ?? false),
                'reason' => $reason,
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moduleOptions(): array
    {
        return array_map(function ($module): array {
            return [
                'moduleKey' => $module->key()->value,
                'category' => $module->category()->value,
                'supportsTeamActivation' => $module->supportsTeamActivation(),
                'readOnly' => $module->category() === ModuleCategory::Core,
            ];
        }, $this->modules->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamModuleStates(int $teamId): array
    {
        return array_map(function ($module) use ($teamId): array {
            $state = $this->activation->effectiveState($module->key()->value, $teamId);

            return [
                'moduleKey' => $module->key()->value,
                'category' => $module->category()->value,
                'teamEnabled' => $state->teamEnabled,
                'effectiveEnabled' => $state->effectiveEnabled,
                'source' => $state->source,
                'version' => $state->teamVersion,
                'supportsTeamActivation' => $module->supportsTeamActivation(),
                'readOnly' => $module->category() === ModuleCategory::Core,
            ];
        }, $this->modules->all());
    }

    private function actorUserId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }

    /**
     * @param  array<mixed>  $values
     */
    private function nullableIntValue(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private function validateSessionLimits(
        ?int $inactivityTimeoutMinutes,
        ?int $sessionMaxLifetimeMinutes,
        ?int $baseInactivityTimeoutMinutes = null,
        ?int $baseSessionMaxLifetimeMinutes = null,
    ): void {
        $effectiveInactivity = $inactivityTimeoutMinutes ?? $baseInactivityTimeoutMinutes ?? $this->securitySessionSettings->inactivityTimeoutMinutes();
        $effectiveMaximum = $sessionMaxLifetimeMinutes ?? $baseSessionMaxLifetimeMinutes ?? $this->globalSessionMaxLifetimeMinutes();

        if ($effectiveInactivity <= $effectiveMaximum) {
            return;
        }

        throw ValidationException::withMessages([
            'inactivity_timeout_minutes' => __('validation.custom.session_limits.inactivity_not_greater_than_maximum'),
        ]);
    }

    private function globalSessionMaxLifetimeMinutes(): int
    {
        $configured = config('atlas.security.sessions.max_lifetime_minutes', 720);

        return max(1, is_numeric($configured) ? (int) $configured : 720);
    }

    /**
     * @return array{dailyLimitMinutes: int, maximumSingleBreakMinutes: int}
     */
    private function globalBreakDefaults(): array
    {
        return [
            'dailyLimitMinutes' => 15,
            'maximumSingleBreakMinutes' => 240,
        ];
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        Request $request,
        string $action,
        string $result,
        string $targetType,
        string $targetPublicId,
        array $before,
        array $after,
    ): void {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->audit->record(new AuditEvent(
            module: 'teams',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: $targetType,
            targetPublicId: $targetPublicId,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            before: $before,
            after: $after,
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }
}
