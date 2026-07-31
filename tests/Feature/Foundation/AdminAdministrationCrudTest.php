<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminAdministrationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_requires_administrative_mode(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $session = ['active_team_public_id' => $activeTeam->public_id];

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin')
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($actor)
            ->withSession([
                ...$session,
                'auth.password_confirmed_at' => now()->unix(),
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_can_update_team_role_and_onboarding_preset_from_separate_edit_routes(): void
    {
        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $editableTeam = Team::query()->create(['name' => 'Legacy Support']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        Role::query()->create([
            'name' => 'operations.viewer',
            'guard_name' => 'web',
            config()->string('permission.column_names.team_foreign_key') => null,
        ]);

        $this->app->make(OnboardingPackageStore::class)->upsert(
            teamPublicId: (string) $activeTeam->public_id,
            name: 'collections.agent',
            label: 'Collections agent',
            initialRoleNames: [StarterRoleName::WorkspaceAccess->value],
            directPermissionNames: ['dashboard'],
            templatePermissionNames: ['dashboard'],
        );
        $packagePublicId = DB::table(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('team_id', $activeTeam->id)
            ->where('name', 'collections.agent')
            ->value('public_id');
        self::assertIsString($packagePublicId);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/teams/'.$editableTeam->public_id, [
                'name' => 'client.support',
                'display_name' => 'Client Support',
            ])
            ->assertRedirect(route('admin.teams.edit', ['team' => $editableTeam->public_id]));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/roles/operations.viewer', [
                'name' => 'operations.reader',
                'display_name' => 'Operations reader',
            ])
            ->assertRedirect(route('admin.authorization.roles.edit', ['role' => 'operations.reader']));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/roles/operations.reader', [
                'name' => 'operations.reader',
                'display_name' => 'Operations reader',
                'permissions' => [CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS],
            ])
            ->assertRedirect(route('admin.authorization.roles.edit', ['role' => 'operations.reader']));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch('/admin/authorization/packages/'.$packagePublicId, [
                'label' => 'Collections specialist',
                'initial_roles' => [StarterRoleName::TeamManagersRead->value],
                'direct_permissions' => ['dashboard', 'admin.users.index'],
            ])
            ->assertRedirect(route('admin.authorization.packages.edit', ['package' => $packagePublicId]));

        self::assertDatabaseHas(DatabaseTable::TEAMS, [
            'public_id' => $editableTeam->public_id,
            'name' => 'client.support',
            'display_name' => 'Client Support',
        ]);
        self::assertDatabaseHas(DatabaseTable::ROLES, [
            'name' => 'operations.reader',
            'guard_name' => 'web',
        ]);
        self::assertDatabaseMissing(DatabaseTable::ROLES, [
            'name' => 'operations.viewer',
            'guard_name' => 'web',
        ]);
        self::assertDatabaseHas(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES, [
            'team_id' => $activeTeam->id,
            'name' => 'collections.agent',
            'label' => 'Collections specialist',
        ]);

        $package = DB::table(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)->where('public_id', $packagePublicId)->first();
        $role = Role::query()->where('name', 'operations.reader')->firstOrFail();
        $permission = DB::table(DatabaseTable::PERMISSIONS)
            ->where('name', CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS)
            ->first(['id']);

        self::assertIsObject($package);
        self::assertIsObject($permission);
        self::assertDatabaseHas(DatabaseTable::ROLE_HAS_PERMISSIONS, [
            'role_id' => $role->id,
            'permission_id' => get_object_vars($permission)['id'] ?? null,
        ]);
        self::assertSame([StarterRoleName::TeamManagersRead->value], $this->jsonStringList($package, 'initial_role_names'));
        self::assertSame(['dashboard', 'admin.users.index'], $this->jsonStringList($package, 'direct_permission_names'));
    }

    public function test_admin_tables_use_validated_server_state_and_saved_team_views_are_audited(): void
    {
        $actor = User::factory()->create(['name' => 'Zeta Owner', 'email' => 'owner@example.test']);
        $matchingUser = User::factory()->create(['name' => 'Alpha Match', 'email' => 'alpha@example.test']);
        User::factory()->create(['name' => 'Beta Other', 'email' => 'beta@example.test']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/users?search=alpha&sort=not_allowed&direction=desc&per_page=25&page=1&columns=name,email,unknown')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.key', 'admin.users')
                ->where('table.state.sort', 'name')
                ->where('table.state.direction', 'desc')
                ->where('table.state.perPage', 25)
                ->where('table.state.columns', ['name', 'email'])
                ->where('table.pagination.total', 1)
                ->where('users.0.publicId', $matchingUser->public_id)
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/users?columns=')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.state.columns', [])
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/table-views', [
                'table_key' => 'admin.users',
                'name' => 'Team active users',
                'type' => 'team',
                'state' => [
                    'sort' => 'email',
                    'direction' => 'asc',
                    'search' => 'alpha',
                    'columns' => ['name', 'email', 'secret'],
                    'columnOrder' => ['email', 'name'],
                ],
            ])
            ->assertRedirect();

        self::assertDatabaseHas(DatabaseTable::TABLE_SAVED_VIEWS, [
            'table_key' => 'admin.users',
            'name' => 'Team active users',
            'type' => 'team',
            'team_id' => $activeTeam->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'shared',
            'action' => 'table_saved_view.created',
            'result' => 'success',
            'actor_public_id' => $actor->public_id,
        ]);

        $view = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('name', 'Team active users')->first();
        self::assertIsObject($view);
        self::assertSame(['name', 'email'], $this->jsonStringList($view, 'state', 'columns'));
        $viewPublicId = $this->recordString($view, 'public_id');

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/table-views', [
                'table_key' => 'admin.users',
                'name' => 'No visible data columns',
                'type' => 'private',
                'state' => [
                    'sort' => 'name',
                    'direction' => 'asc',
                    'columns' => [],
                    'columnOrder' => [],
                ],
            ])
            ->assertRedirect();

        $emptyColumnsView = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('name', 'No visible data columns')->first();
        self::assertIsObject($emptyColumnsView);
        self::assertSame([], $this->jsonStringList($emptyColumnsView, 'state', 'columns'));

        $this->actingAs($actor)
            ->withSession($session)
            ->patch("/admin/table-views/{$viewPublicId}", [
                'name' => 'Team active users',
                'state' => [
                    'sort' => 'email',
                    'direction' => 'asc',
                    'search' => 'alpha',
                    'columns' => ['name', 'email'],
                    'columnOrder' => ['email', 'name'],
                    'filters' => [
                        'module' => 'authorization',
                        'team' => $activeTeam->public_id,
                        'security' => 'yes',
                        'empty' => '',
                        'flag' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $updatedView = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('name', 'Team active users')->first();
        self::assertIsObject($updatedView);
        $updatedFilters = $this->jsonObject($updatedView, 'state', 'filters');
        self::assertSame('authorization', $updatedFilters['module'] ?? null);
        self::assertSame($activeTeam->public_id, $updatedFilters['team'] ?? null);
        self::assertSame('yes', $updatedFilters['security'] ?? null);
        self::assertNull($updatedFilters['empty'] ?? null);
        self::assertTrue($updatedFilters['flag'] ?? false);
    }

    public function test_admin_permissions_catalog_is_readonly_table_with_effective_state(): void
    {
        $actor = User::factory()->create(['name' => 'Catalog Viewer']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($activeTeam))
            ->get('/admin/authorization/permissions?search=admin.authorization.permissions&columns=name,module,effective,description')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Permissions')
                ->where('table.key', 'admin.authorization.permissions')
                ->where('table.state.sort', 'name')
                ->where('table.state.columns', ['name', 'module', 'effective', 'description'])
                ->where('table.pagination.total', 1)
                ->where('permissions.0.name', CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS)
                ->where('permissions.0.module', 'authorization')
                ->where('permissions.0.teamScoped', true)
                ->where('permissions.0.moduleActivation', 'active')
                ->where('permissions.0.assigned', true)
                ->where('permissions.0.effective', true)
                ->where('permissions.0.ineffectiveReason', null)
                ->has('table.exports')
                ->where('auth.availableAdminRoutes', fn ($routes): bool => $this->stringListContains($routes, 'admin.authorization.permissions.index'))
            );
    }

    public function test_admin_permissions_catalog_applies_filters_before_pagination(): void
    {
        $actor = User::factory()->create(['name' => 'Catalog Filter Viewer']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($activeTeam))
            ->get('/admin/authorization/permissions?module=users&effective=yes&teamScoped=yes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Permissions')
                ->where('table.state.filters.module', 'users')
                ->where('table.state.filters.effective', 'yes')
                ->where('table.state.filters.teamScoped', 'yes')
                ->where('filterOptions.modules', fn ($modules): bool => $this->stringListContains($modules, 'users'))
                ->where('permissions', fn ($rows): bool => $this->nonEmptyEveryRow($rows,
                    fn (array $row): bool => ($row['module'] ?? null) === 'users'
                        && ($row['effective'] ?? false) === true
                        && ($row['teamScoped'] ?? false) === true
                ))
            );
    }

    public function test_admin_users_table_applies_filters_before_pagination(): void
    {
        $actor = User::factory()->create(['name' => 'User Filter Admin']);
        User::factory()->create(['name' => 'Active Visible User']);
        User::factory()->inactive()->create(['name' => 'Inactive Filtered User']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($activeTeam))
            ->get('/admin/users?status=inactive')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.state.filters.status', 'inactive')
                ->where('table.pagination.total', 1)
                ->where('users.0.name', 'Inactive Filtered User')
                ->where('users.0.isActive', false)
            );
    }

    public function test_admin_teams_workflow_exposes_table_and_create_edit_contracts(): void
    {
        $actor = User::factory()->create(['name' => 'Team Admin']);
        $member = User::factory()->create(['name' => 'Team Member']);
        $activeTeam = Team::query()->create(['name' => 'operations', 'display_name' => 'Operations']);
        $inactiveTeam = Team::query()->create(['name' => 'archive', 'display_name' => 'Archive', 'is_active' => false]);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($member, $activeTeam, StarterRoleName::WorkspaceAccess->value);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams?status=active&members=with')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Index')
                ->where('table.key', 'admin.teams')
                ->where('table.state.filters.status', 'active')
                ->where('table.state.filters.members', 'with')
                ->where('teams', fn ($teams): bool => $this->containsRow($teams,
                    fn (array $team): bool => ($team['publicId'] ?? null) === $activeTeam->public_id
                        && ($team['displayName'] ?? null) === 'Operations'
                        && ($team['membersCount'] ?? 0) >= 1
                ) && $this->everyRow($teams,
                    fn (array $team): bool => ($team['isActive'] ?? false) === true
                        && ($team['membersCount'] ?? 0) > 0
                ))
                ->has('table.exports')
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Create')
                ->where('userOptions', fn ($users): bool => $this->containsRow($users,
                    fn (array $user): bool => ($user['value'] ?? null) === $member->public_id
                ))
                ->where('roleOptions', fn ($roles): bool => $this->optionsContainValue($roles, StarterRoleName::WorkspaceAccess->value))
                ->where('permissionOptions', fn ($permissions): bool => $this->optionsContainValue($permissions, CoreAuthorizationPermissionCatalog::DASHBOARD))
                ->has('rolePermissionMap')
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/teams/'.$activeTeam->public_id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Edit')
                ->where('team.publicId', $activeTeam->public_id)
                ->where('team.name', 'operations')
                ->where('team.displayName', 'Operations')
                ->where('memberships', fn ($memberships): bool => $this->containsRow($memberships,
                    fn (array $membership): bool => ($membership['userPublicId'] ?? null) === $member->public_id
                        && $this->stringListContains($membership['roleNames'] ?? [], StarterRoleName::WorkspaceAccess->value)
                ))
                ->has('assignableUsers')
                ->has('rolePermissionMap')
            );
    }

    public function test_admin_roles_workflow_exposes_global_role_index_and_create_edit_contracts(): void
    {
        $actor = User::factory()->create(['name' => 'Role Admin']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        Role::query()->create([
            'name' => 'operations.viewer',
            'guard_name' => 'web',
            config()->string('permission.column_names.team_foreign_key') => null,
        ]);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/roles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles')
                ->where('table.key', 'admin.authorization.roles')
                ->where('roles', fn ($rows): bool => $this->nonEmptyEveryRow($rows,
                    fn (array $row): bool => ($row['guard'] ?? null) === 'web'
                        && ($row['assignedUsersCount'] ?? null) !== null
                        && ! array_key_exists('teamId', $row)
                ))
                ->where('auth.availableAdminRoutes', fn ($routes): bool => $this->stringListContains($routes, 'admin.authorization.roles.index'))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/roles?assignment=unassigned&permissions=without')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles')
                ->where('table.state.filters.assignment', 'unassigned')
                ->where('table.state.filters.permissions', 'without')
                ->where('roles', fn ($rows): bool => $this->nonEmptyEveryRow($rows,
                    fn (array $row): bool => ($row['assignedUsersCount'] ?? 1) === 0
                        && ($row['permissionsCount'] ?? 1) === 0
                ))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/roles/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles/Create')
                ->where('permissionOptions', fn ($permissions): bool => $this->optionsContainValue($permissions, CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/authorization/roles', [
                'name' => 'operations.reader',
                'display_name' => 'Operations reader',
                'permissions' => [CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS],
            ])
            ->assertRedirect(route('admin.authorization.roles.index'));

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/roles/operations.reader/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Roles/Edit')
                ->where('role.name', 'operations.reader')
                ->where('role.displayName', 'Operations reader')
                ->where('role.guard', 'web')
                ->where('role.permissions', [CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS])
                ->where('permissionOptions', fn ($permissions): bool => $this->optionsContainValue($permissions, CoreAuthorizationPermissionCatalog::DASHBOARD))
            );
    }

    public function test_admin_onboarding_presets_workflow_exposes_table_and_create_edit_contracts(): void
    {
        $actor = User::factory()->create(['name' => 'Preset Admin']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $anotherTeam = Team::query()->create(['name' => 'Field Team']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->app->make(OnboardingPackageStore::class)->upsert(
            teamPublicId: (string) $activeTeam->public_id,
            name: 'collections.agent',
            label: 'Collections agent',
            initialRoleNames: [StarterRoleName::WorkspaceAccess->value],
            directPermissionNames: [CoreAuthorizationPermissionCatalog::DASHBOARD],
            templatePermissionNames: [CoreAuthorizationPermissionCatalog::DASHBOARD],
        );

        $packagePublicId = DB::table(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->where('team_id', $activeTeam->id)
            ->where('name', 'collections.agent')
            ->value('public_id');
        self::assertIsString($packagePublicId);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/packages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages')
                ->where('table.key', 'admin.authorization.packages')
                ->where('packages.0.publicId', $packagePublicId)
                ->where('packages.0.teamName', 'Operations')
                ->where('packages.0.label', 'Collections agent')
                ->where('packages.0.initialRoles', [StarterRoleName::WorkspaceAccess->value])
                ->where('packages.0.directPermissions', [CoreAuthorizationPermissionCatalog::DASHBOARD])
                ->where('auth.availableAdminRoutes', fn ($routes): bool => $this->stringListContains($routes, 'admin.authorization.packages.index'))
                ->has('table.exports')
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/packages?status=active&team='.$activeTeam->public_id.'&roles=with&directPermissions=with&templatePermissions=with')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages')
                ->where('table.state.filters.status', 'active')
                ->where('table.state.filters.team', $activeTeam->public_id)
                ->where('table.state.filters.roles', 'with')
                ->where('table.state.filters.directPermissions', 'with')
                ->where('table.state.filters.templatePermissions', 'with')
                ->where('filterOptions.teams', fn ($teams): bool => $this->containsRow($teams,
                    fn (array $team): bool => ($team['value'] ?? null) === $activeTeam->public_id
                ))
                ->where('packages', fn ($packages): bool => $this->nonEmptyEveryRow($packages,
                    fn (array $package): bool => ($package['teamPublicId'] ?? null) === $activeTeam->public_id
                        && ($package['isActive'] ?? false) === true
                        && count(self::listValue($package['initialRoles'] ?? [])) > 0
                        && count(self::listValue($package['directPermissions'] ?? [])) > 0
                        && count(self::listValue($package['templatePermissions'] ?? [])) > 0
                ))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/packages/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages/Create')
                ->where('teamOptions', fn ($teams): bool => $this->containsRow($teams,
                    fn (array $team): bool => ($team['value'] ?? null) === $anotherTeam->public_id
                ))
                ->where('roleOptions', fn ($roles): bool => $this->optionsContainValue($roles, StarterRoleName::WorkspaceAccess->value))
                ->where('permissionOptions', fn ($permissions): bool => $this->optionsContainValue($permissions, CoreAuthorizationPermissionCatalog::DASHBOARD))
            );

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/authorization/packages/'.$packagePublicId.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Authorization/Packages/Edit')
                ->where('package.publicId', $packagePublicId)
                ->where('package.teamName', 'Operations')
                ->where('package.name', 'collections.agent')
                ->where('package.label', 'Collections agent')
                ->where('package.initialRoles', [StarterRoleName::WorkspaceAccess->value])
                ->where('package.directPermissions', [CoreAuthorizationPermissionCatalog::DASHBOARD])
                ->where('roleOptions', fn ($roles): bool => $this->optionsContainValue($roles, StarterRoleName::WorkspaceAccess->value))
                ->where('permissionOptions', fn ($permissions): bool => $this->optionsContainValue($permissions, CoreAuthorizationPermissionCatalog::DASHBOARD))
            );
    }

    public function test_admin_can_add_and_remove_user_team_access_with_authorization_cleanup_and_session_invalidation(): void
    {
        $actor = User::factory()->create(['name' => 'Admin Actor']);
        $target = User::factory()->create(['name' => 'Target User']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $managedTeam = Team::query()->create(['name' => 'Field Team']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $session = $this->adminSession($activeTeam);

        $this->actingAs($actor)
            ->withSession($session)
            ->get('/admin/users/'.$target->public_id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Edit')
                ->where('assignableTeams.0.value', $managedTeam->public_id)
                ->where('teamMemberships', [])
                ->has('packages')
                ->has('copySources')
                ->has('rolePermissionMap'));

        $this->actingAs($actor)
            ->withSession($session)
            ->post('/admin/users/'.$target->public_id.'/teams', [
                'team_public_id' => $managedTeam->public_id,
            ])
            ->assertRedirect(route('admin.users.edit', ['user' => $target->public_id]));

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $managedTeam->id,
            'user_id' => $target->id,
            'valid_to' => null,
        ]);

        $role = Role::query()->where('name', StarterRoleName::WorkspaceAccess->value)->firstOrFail();
        $permission = Permission::query()->where('name', CoreAuthorizationPermissionCatalog::DASHBOARD)->firstOrFail();

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $target->id,
            'team_id' => $managedTeam->id,
        ]);
        DB::table(DatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $target->id,
            'team_id' => $managedTeam->id,
        ]);

        $this->recordSessionMetadata($target, $managedTeam, 'managed-team-session');

        self::assertCount(1, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $target->public_id));

        $this->actingAs($actor)
            ->withSession($session)
            ->delete('/admin/users/'.$target->public_id.'/teams/'.$managedTeam->public_id, [
                'reason' => 'User moved to another team.',
            ])
            ->assertRedirect(route('admin.users.edit', ['user' => $target->public_id]));

        $assignment = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $managedTeam->id)
            ->where('user_id', $target->id)
            ->first(['valid_to']);

        self::assertIsObject($assignment);
        self::assertNotNull(get_object_vars($assignment)['valid_to'] ?? null);
        self::assertDatabaseMissing(DatabaseTable::MODEL_HAS_ROLES, [
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $target->id,
            'team_id' => $managedTeam->id,
        ]);
        self::assertDatabaseMissing(DatabaseTable::MODEL_HAS_PERMISSIONS, [
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $target->id,
            'team_id' => $managedTeam->id,
        ]);
        self::assertCount(0, $this->app->make(UserSessionRegistry::class)->activeForUser((string) $target->public_id));
        $this->assertDatabaseHas(DatabaseTable::AUDIT_EVENTS, [
            'module' => 'teams',
            'action' => 'team.user_access_removed',
            'result' => 'succeeded',
            'actor_public_id' => $actor->public_id,
            'target_public_id' => $target->public_id,
            'team_public_id' => $managedTeam->public_id,
        ]);
    }

    public function test_admin_team_creation_accepts_initial_members_with_roles_and_permissions(): void
    {
        $actor = User::factory()->create(['name' => 'Admin Actor']);
        $member = User::factory()->create(['name' => 'Team Member']);
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($activeTeam))
            ->post('/admin/teams', [
                'name' => 'new.integrated.team',
                'display_name' => 'New Integrated Team',
                'user_assignments' => [
                    [
                        'user_public_id' => $member->public_id,
                        'role_names' => [StarterRoleName::TeamManagersRead->value],
                        'direct_permission_names' => [CoreAuthorizationPermissionCatalog::DASHBOARD],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.teams.index'));

        $createdTeam = Team::query()->where('name', 'new.integrated.team')->firstOrFail();
        $managerRole = Role::query()->where('name', StarterRoleName::TeamManagersRead->value)->firstOrFail();
        $permission = Permission::query()->where('name', CoreAuthorizationPermissionCatalog::DASHBOARD)->firstOrFail();

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $createdTeam->id,
            'user_id' => $member->id,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_ROLES, [
            'role_id' => $managerRole->id,
            'model_id' => $member->id,
            'team_id' => $createdTeam->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_PERMISSIONS, [
            'permission_id' => $permission->id,
            'model_id' => $member->id,
            'team_id' => $createdTeam->id,
        ]);
    }

    private function assignStarterRoleInTeam(User $user, Team $team, string $roleName): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', $roleName)->firstOrFail();

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function adminSession(Team $team): array
    {
        return [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];
    }

    private function recordSessionMetadata(User $user, Team $team, string $sessionId): void
    {
        $session = $this->app['session.store'];
        $session->setId($sessionId);
        $session->start();
        $session->put('active_team_public_id', (string) $team->public_id);

        $request = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0']);
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn (): User => $user);

        $this->app->make(UserSessionRegistry::class)->touch($request);
        $session->save();
        $session->setId('admin-session');
        $session->start();
        $session->flush();
    }

    /**
     * @return list<string>
     */
    private function jsonStringList(object $record, string $property, ?string $nestedProperty = null): array
    {
        $values = get_object_vars($record);
        $decoded = json_decode(is_string($values[$property] ?? null) ? $values[$property] : '[]', true);

        if ($nestedProperty !== null && is_array($decoded)) {
            $decoded = $decoded[$nestedProperty] ?? [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(object $record, string $property, ?string $nestedProperty = null): array
    {
        $values = get_object_vars($record);
        $decoded = json_decode(is_string($values[$property] ?? null) ? $values[$property] : '[]', true);

        if ($nestedProperty !== null && is_array($decoded)) {
            $decoded = $decoded[$nestedProperty] ?? [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function recordString(object $record, string $property): string
    {
        $values = get_object_vars($record);
        $value = $values[$property] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function optionsContainValue(mixed $options, string $value): bool
    {
        return $this->containsRow($options, static fn (array $option): bool => ($option['value'] ?? null) === $value);
    }

    private function stringListContains(mixed $values, string $value): bool
    {
        $values = self::arrayValue($values);

        foreach ($values as $key => $item) {
            if ($key === $value || $item === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function containsRow(mixed $rows, callable $predicate): bool
    {
        foreach (self::listValue($rows) as $row) {
            if (is_array($row) && $predicate(self::stringKeyedArray($row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function everyRow(mixed $rows, callable $predicate): bool
    {
        foreach (self::listValue($rows) as $row) {
            if (! is_array($row) || ! $predicate(self::stringKeyedArray($row))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function nonEmptyEveryRow(mixed $rows, callable $predicate): bool
    {
        return self::listValue($rows) !== [] && $this->everyRow($rows, $predicate);
    }

    /**
     * @return list<mixed>
     */
    private static function listValue(mixed $value): array
    {
        return array_values(self::arrayValue($value));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
