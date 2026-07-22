<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Identity\Application\Public\Contracts\UserCredentialAccountDirectory;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
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
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::TEAMS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $rows = array_values(Team::query()
            ->get(['id', 'public_id', 'name', 'is_active', 'created_at', 'updated_at'])
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'publicId' => (string) $team->public_id,
                'name' => $team->name,
                'isActive' => $team->is_active,
                'createdAt' => $team->created_at?->toISOString() ?? '',
                'updatedAt' => $team->updated_at?->toISOString() ?? '',
            ])
            ->all());
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $result->rows,
            'table' => $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Teams/Create', [
            'userOptions' => $this->userOptions(),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'moduleOptions' => $this->moduleOptions(),
        ]);
    }

    public function edit(string $team): Response
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        return Inertia::render('Admin/Teams/Edit', [
            'team' => [
                'publicId' => (string) $record->public_id,
                'name' => $record->name,
                'isActive' => $record->is_active,
            ],
            'memberships' => array_map(function ($membership) use ($record): array {
                $assignments = $this->authorization->assignmentsForUserTeam($membership->userPublicId, (string) $record->public_id);

                return [
                    'userPublicId' => $membership->userPublicId,
                    'userName' => $membership->userName,
                    'userEmail' => $membership->userEmail,
                    'validFrom' => $membership->validFrom,
                    'validTo' => $membership->validTo,
                    'roleNames' => $assignments->roleNames,
                    'directPermissionNames' => $assignments->directPermissionNames,
                ];
            }, $this->memberships->activeMembershipsForTeam((string) $record->public_id)),
            'assignableUsers' => $this->memberships->assignableUsersForTeam((string) $record->public_id),
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'moduleStates' => $this->teamModuleStates($record->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Team::class, 'name')],
            'user_assignments' => ['array'],
            'user_assignments.*.user_public_id' => ['nullable', 'string'],
            'user_assignments.*.role_names' => ['array'],
            'user_assignments.*.role_names.*' => ['string'],
            'user_assignments.*.direct_permission_names' => ['array'],
            'user_assignments.*.direct_permission_names.*' => ['string'],
            'module_overrides' => ['array'],
            'module_overrides.*.module_key' => ['required', 'string'],
            'module_overrides.*.enabled' => ['required', 'boolean'],
            'module_overrides.*.reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $userAssignments = $this->userAssignments(is_array($validated) ? $validated : []);

        $this->validateUserAssignments($userAssignments);

        $record = Team::query()->create([
            'name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '',
        ]);

        $this->recordAudit($request, 'team.created', 'succeeded', 'team', (string) $record->public_id, [], [
            'name' => $record->name,
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
            }

            foreach ($this->moduleOverrides(is_array($validated) ? $validated : []) as $override) {
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

        return redirect()->route('admin.teams.index')->with('success', 'Team was created.');
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
        ]);

        $before = [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ];
        $record->forceFill([
            'name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '',
        ])->save();

        $this->recordAudit($request, 'team.updated', 'succeeded', 'team', (string) $record->public_id, $before, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('success', 'Team was updated.');
    }

    public function activate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, true);

        return redirect()->route('admin.teams.index')->with('success', 'Team was activated.');
    }

    public function deactivate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, false);

        return redirect()->route('admin.teams.index')->with('success', 'Team was deactivated.');
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

        return redirect()->route('admin.teams.index')->with('success', 'Team delete was attempted.');
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
     * @return list<array{user_public_id: string, role_names: list<string>, direct_permission_names: list<string>}>
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
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{user_public_id: string, role_names: list<string>, direct_permission_names: list<string>}>  $assignments
     */
    private function validateUserAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            if ($this->accounts->publicIdExists($assignment['user_public_id'])) {
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
