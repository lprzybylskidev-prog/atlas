<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Notifications\UserEmailVerificationNotification;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Infrastructure\Notifications\FirstPasswordSetupNotification;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Shared\Infrastructure\Database\DatabaseTable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_creation_route_uses_normal_use_case_and_onboarding_package(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->createOnboardingPackage((string) $team->public_id, 'collections.agent', StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Created In Admin',
                'email' => 'created.admin@example.test',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'package',
                        'onboarding_package' => 'collections.agent',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.admin@example.test')->firstOrFail();

        self::assertSame('sensitive', $created->account_sensitivity);
        self::assertSame(User::DEFAULT_AVATAR_COLOR, $created->avatar_color);
        self::assertDatabaseHas(DatabaseTable::USER_ONBOARDING_PACKAGES, [
            'user_id' => $created->id,
            'team_id' => $team->id,
            'package_name' => 'collections.agent',
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_accepts_selected_account_sensitivity(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Created Normal Account',
                'email' => 'created.normal@example.test',
                'account_sensitivity' => 'normal',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'manual',
                        'role_names' => [StarterRoleName::WorkspaceAccess->value],
                        'direct_permission_names' => [],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.normal@example.test')->firstOrFail();

        self::assertSame('normal', $created->account_sensitivity);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_accepts_user_team_session_limit_overrides(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Created Session Limits',
                'email' => 'created.session.limits@example.test',
                'account_sensitivity' => 'normal',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'manual',
                        'role_names' => [StarterRoleName::WorkspaceAccess->value],
                        'direct_permission_names' => [],
                        'inactivity_timeout_minutes' => 20,
                        'session_max_lifetime_minutes' => 120,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.session.limits@example.test')->firstOrFail();

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $team->id,
            'user_id' => $created->id,
            'inactivity_timeout_minutes' => 20,
            'session_max_lifetime_minutes' => 120,
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_accepts_regular_break_daily_limit_override(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Created Break Limit',
                'email' => 'created.break.limit@example.test',
                'account_sensitivity' => 'normal',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'manual',
                        'role_names' => [StarterRoleName::WorkspaceAccess->value],
                        'direct_permission_names' => [TimeTrackingPermissionCatalog::USER_REPORT],
                        'break_daily_limit_minutes' => 45,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.break.limit@example.test')->firstOrFail();
        $assignmentId = $this->assignmentId($created, $team);

        self::assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAK_POLICIES, [
            'scope_type' => 'user_team',
            'scope_id' => $assignmentId,
            'daily_limit_seconds' => 2700,
            'maximum_single_break_seconds' => 14400,
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_rejects_inactivity_timeout_longer_than_session_lifetime(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Invalid Session Limits',
                'email' => 'invalid.session.limits@example.test',
                'account_sensitivity' => 'normal',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'manual',
                        'role_names' => [StarterRoleName::WorkspaceAccess->value],
                        'direct_permission_names' => [],
                        'inactivity_timeout_minutes' => 90,
                        'session_max_lifetime_minutes' => 30,
                    ],
                ],
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['team_assignments']);

        self::assertDatabaseMissing(DatabaseTable::USERS, [
            'email' => 'invalid.session.limits@example.test',
        ]);
        Notification::assertNothingSent();
    }

    public function test_admin_user_team_authorization_updates_session_limit_overrides(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target.session@example.test',
            'account_sensitivity' => 'normal',
        ]);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->patch('/admin/users/'.$target->public_id.'/teams/'.$team->public_id.'/authorization', [
                'source' => 'manual',
                'role_names' => [StarterRoleName::WorkspaceAccess->value],
                'direct_permission_names' => [],
                'inactivity_timeout_minutes' => 15,
                'session_max_lifetime_minutes' => 90,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $team->id,
            'user_id' => $target->id,
            'inactivity_timeout_minutes' => 15,
            'session_max_lifetime_minutes' => 90,
        ]);
    }

    public function test_admin_user_team_authorization_updates_regular_break_daily_limit_override(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($target, $team, StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->patch('/admin/users/'.$target->public_id.'/teams/'.$team->public_id.'/authorization', [
                'source' => 'manual',
                'role_names' => [StarterRoleName::WorkspaceAccess->value],
                'direct_permission_names' => [TimeTrackingPermissionCatalog::USER_REPORT],
                'break_daily_limit_minutes' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        self::assertDatabaseHas(DatabaseTable::TIME_TRACKING_BREAK_POLICIES, [
            'scope_type' => 'user_team',
            'scope_id' => $this->assignmentId($target, $team),
            'daily_limit_seconds' => 1200,
            'maximum_single_break_seconds' => 14400,
        ]);
    }

    public function test_admin_user_creation_can_copy_authorization_from_existing_user(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $source = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($source, $team, StarterRoleName::TeamManagersRead->value);
        $this->assignDirectPermissionInTeam($source, $team, 'admin.authorization.permissions.index');

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users', [
                'name' => 'Copied Permissions',
                'email' => 'copied.permissions@example.test',
                'account_sensitivity' => 'sensitive',
                'team_assignments' => [
                    [
                        'team_public_id' => $team->public_id,
                        'source' => 'copy',
                        'copy_authorization_from_user' => $source->public_id,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'copied.permissions@example.test')->firstOrFail();
        $managerRole = Role::query()->where('name', StarterRoleName::TeamManagersRead->value)->firstOrFail();
        $permission = Permission::query()->where('name', 'admin.authorization.permissions.index')->firstOrFail();

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $team->id,
            'user_id' => $created->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_ROLES, [
            'role_id' => $managerRole->id,
            'model_id' => $created->id,
            'team_id' => $team->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_PERMISSIONS, [
            'permission_id' => $permission->id,
            'model_id' => $created->id,
            'team_id' => $team->id,
        ]);
        self::assertDatabaseMissing(DatabaseTable::USER_ONBOARDING_PACKAGES, [
            'user_id' => $created->id,
        ]);
        Notification::assertSentOnDemand(FirstPasswordSetupNotification::class);
    }

    public function test_admin_user_creation_rejects_copy_source_without_selected_team_access(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $source = User::factory()->create();
        $sourceTeam = Team::query()->create(['name' => 'Collections North']);
        $targetTeam = Team::query()->create(['name' => 'Collections South']);
        $this->assignStarterRoleInTeam($actor, $targetTeam, StarterRoleName::Administrator->value);
        $this->assignStarterRoleInTeam($source, $sourceTeam, StarterRoleName::TeamManagersRead->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($targetTeam))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Invalid Copy Source',
                'email' => 'invalid.copy.source@example.test',
                'account_sensitivity' => 'sensitive',
                'team_assignments' => [
                    [
                        'team_public_id' => $targetTeam->public_id,
                        'source' => 'copy',
                        'copy_authorization_from_user' => $source->public_id,
                    ],
                ],
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['team_assignments']);

        self::assertDatabaseMissing(DatabaseTable::USERS, [
            'email' => 'invalid.copy.source@example.test',
        ]);
        Notification::assertNothingSent();
    }

    public function test_admin_user_creation_rejects_preset_from_another_team(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $sourceTeam = Team::query()->create(['name' => 'Collections North']);
        $targetTeam = Team::query()->create(['name' => 'Collections South']);
        $this->assignStarterRoleInTeam($actor, $targetTeam, StarterRoleName::Administrator->value);
        $this->createOnboardingPackage((string) $sourceTeam->public_id, 'north.agent', StarterRoleName::WorkspaceAccess->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($targetTeam))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Invalid Preset Team',
                'email' => 'invalid.preset.team@example.test',
                'account_sensitivity' => 'sensitive',
                'team_assignments' => [
                    [
                        'team_public_id' => $targetTeam->public_id,
                        'source' => 'package',
                        'onboarding_package' => 'north.agent',
                    ],
                ],
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['team_assignments']);

        self::assertDatabaseMissing(DatabaseTable::USERS, [
            'email' => 'invalid.preset.team@example.test',
        ]);
        Notification::assertNothingSent();
    }

    public function test_admin_user_creation_validation_errors_follow_shared_locale_field_names(): void
    {
        Notification::fake();
        app()->setLocale('pl');

        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Invalid Team Assignment',
                'email' => 'invalid.team.assignment@example.test',
                'account_sensitivity' => 'sensitive',
                'team_assignments' => [
                    [
                        'source' => 'manual',
                    ],
                ],
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors([
                'team_assignments.0.team_public_id' => 'Pole zespół w przypisaniu jest wymagane.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_admin_user_creation_accepts_explicit_team_role_and_permission_assignments(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $activeTeam = Team::query()->create(['name' => 'Operations']);
        $assignedTeam = Team::query()->create(['name' => 'Legal']);
        $this->assignStarterRoleInTeam($actor, $activeTeam, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($activeTeam))
            ->post('/admin/users', [
                'name' => 'Explicit Assignments',
                'email' => 'explicit.assignments@example.test',
                'account_sensitivity' => 'sensitive',
                'team_assignments' => [
                    [
                        'team_public_id' => $assignedTeam->public_id,
                        'source' => 'manual',
                        'role_names' => [StarterRoleName::TeamManagersRead->value],
                        'direct_permission_names' => [CoreAuthorizationPermissionCatalog::DASHBOARD],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'explicit.assignments@example.test')->firstOrFail();
        $managerRole = Role::query()->where('name', StarterRoleName::TeamManagersRead->value)->firstOrFail();
        $permission = Permission::query()->where('name', CoreAuthorizationPermissionCatalog::DASHBOARD)->firstOrFail();

        self::assertDatabaseHas(DatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'team_id' => $assignedTeam->id,
            'user_id' => $created->id,
            'valid_to' => null,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_ROLES, [
            'role_id' => $managerRole->id,
            'model_id' => $created->id,
            'team_id' => $assignedTeam->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::MODEL_HAS_PERMISSIONS, [
            'permission_id' => $permission->id,
            'model_id' => $created->id,
            'team_id' => $assignedTeam->id,
        ]);
    }

    public function test_admin_can_create_onboarding_package_from_admin_panel(): void
    {
        $actor = User::factory()->create();
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/authorization/packages', [
                'team_public_id' => $team->public_id,
                'name' => 'legal.assistant',
                'label' => 'Legal assistant',
                'initial_roles' => [StarterRoleName::WorkspaceAccess->value],
                'direct_permissions' => ['dashboard'],
            ])
            ->assertRedirect(route('admin.authorization.packages.index'));

        self::assertDatabaseHas(DatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES, [
            'name' => 'legal.assistant',
            'label' => 'Legal assistant',
        ]);
    }

    public function test_admin_can_require_user_email_verification_again_without_resetting_password(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $target = User::factory()->create([
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
        ]);
        $firstPasswordSetAt = $this->timestampOf($target->first_password_set_at);
        $team = Team::query()->create(['name' => 'Operations']);
        $this->assignStarterRoleInTeam($actor, $team, StarterRoleName::Administrator->value);

        $this->actingAs($actor)
            ->withSession($this->adminSession($team))
            ->post('/admin/users/'.$target->public_id.'/require-email-verification')
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();

        self::assertNull($target->email_verified_at);
        self::assertSame($firstPasswordSetAt, $this->timestampOf($target->first_password_set_at));
        Notification::assertSentTo($target, UserEmailVerificationNotification::class);
        Notification::assertNotSentTo($target, FirstPasswordSetupNotification::class);
    }

    private function timestampOf(mixed $value): int
    {
        self::assertInstanceOf(DateTimeInterface::class, $value);

        return $value->getTimestamp();
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

    private function assignDirectPermissionInTeam(User $user, Team $team, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();

        DB::table(DatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function assignmentId(User $user, Team $team): int
    {
        $assignmentId = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereNull('valid_to')
            ->value('id');

        self::assertIsNumeric($assignmentId);

        return (int) $assignmentId;
    }

    private function createOnboardingPackage(string $teamPublicId, string $name, string $roleName): void
    {
        $this->app->make(OnboardingPackageStore::class)->upsert(
            teamPublicId: $teamPublicId,
            name: $name,
            label: $name,
            initialRoleNames: [$roleName],
            directPermissionNames: [],
            templatePermissionNames: [CoreAuthorizationPermissionCatalog::DASHBOARD],
        );
    }
}
