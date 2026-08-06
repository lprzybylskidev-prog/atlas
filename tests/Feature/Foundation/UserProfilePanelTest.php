<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Persistence\NotificationsDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamSessionLimitSettings;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class UserProfilePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_panel_hides_notification_types_the_user_cannot_receive(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->has('profile.notificationTypes', 0));
    }

    public function test_user_profile_panel_exposes_effective_inactivity_timeout_only(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->app->make(UserTeamSessionLimitSettings::class)->setUserTeamOverrides($user->public_id, $team->public_id, 18, 120);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('profile.session.inactivityTimeoutMinutes', 18)
                ->missing('profile.session.sessionMaxLifetimeMinutes'));
    }

    public function test_user_profile_panel_exposes_break_daily_limit_only_for_tracked_user_team(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::USER_REPORT);
        $this->app->make(UserBreakPolicySettings::class)->setUserTeamOverrides($user->public_id, $team->public_id, 25, null);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('profile.timeTracking.breakDailyLimitMinutes', null));

        $this->enableTracking($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('profile.timeTracking.breakDailyLimitMinutes', 25));
    }

    public function test_user_profile_panel_exposes_eligible_notification_body_previews(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, ReportsPermissionCatalog::REQUEST);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->has('profile.notificationTypes', 2)
                ->where('profile.notificationTypes.0.type', 'report_export.available')
                ->where('profile.notificationTypes.0.bodyPreviewKey', 'notifications.exports.available.body')
                ->where('profile.notificationTypes.0.bodyPreviewParams.report_name', 'Raport czasu pracy'));
    }

    public function test_notification_preference_update_only_touches_visible_types(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE);
        $this->assignDirectPermissionInTeam($user, $team, ReportsPermissionCatalog::REQUEST);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk();

        $address = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('primary', true)
            ->first(['id', 'public_id']);

        self::assertIsObject($address);
        $addressId = $this->numericId($address->id ?? null);

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
            ->where('notification_email_address_id', $addressId)
            ->where('team_id', $team->id)
            ->where('notification_type', 'managed_process.succeeded')
            ->update(['enabled' => true]);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->patch('/user/notification-emails/'.$this->stringValue($address->public_id ?? null), [
                'enabled_types' => ['report_export.available'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES, [
            'notification_email_address_id' => $addressId,
            'team_id' => $team->id,
            'notification_type' => 'report_export.available',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES, [
            'notification_email_address_id' => $addressId,
            'team_id' => $team->id,
            'notification_type' => 'report_export.failed',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES, [
            'notification_email_address_id' => $addressId,
            'team_id' => $team->id,
            'notification_type' => 'managed_process.succeeded',
            'enabled' => true,
        ]);
    }

    public function test_user_profile_panel_hides_time_tracking_notification_types(): void
    {
        [$user, $team] = $this->userWithTeam();
        $this->activateTimeTracking($team);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::BREAK_SHOW);
        $this->assignDirectPermissionInTeam($user, $team, TimeTrackingPermissionCatalog::OTHER_WORK_SHOW);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->has('profile.notificationTypes', 0));

        $this->enableTracking($user, $team);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->has('profile.notificationTypes', 0));
    }

    public function test_user_can_update_and_remove_profile_avatar_image(): void
    {
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');
        Config::set('atlas.files.fake_scanner_result', 'clean');
        Config::set('atlas.files.allowed_extensions', ['png', 'jpg', 'jpeg', 'webp']);
        Config::set('atlas.files.allowed_mime_types', ['image/png', 'image/jpeg', 'image/webp']);

        [$user, $team] = $this->userWithTeam();
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE_AVATAR_IMAGE);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE_AVATAR_UPDATE);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->post('/user/avatar', [
                'avatar_color' => '#fef3c7',
                'avatar_image' => self::fakePngAvatar(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        self::assertSame('#fef3c7', $user->avatar_color);
        self::assertIsString($user->avatar_image_file_public_id);

        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('public_id', $user->avatar_image_file_public_id)->first();
        self::assertIsObject($file);
        self::assertSame('clean', $file->scan_state);
        $storedPath = self::stringValue($file->path);
        Storage::disk('atlas_files')->assertExists($storedPath);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Panel')
                ->where('auth.user.avatar.color', '#fef3c7')
                ->where('auth.user.avatar.imageUrl', route('users.profile.avatar-image', absolute: false))
                ->where('profile.avatar.imageUrl', route('users.profile.avatar-image', absolute: false)));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/avatar-image')
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->post('/user/avatar', [
                'avatar_color' => '#14532d',
                'remove_avatar_image' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        self::assertSame('#14532d', $user->avatar_color);
        self::assertNull($user->avatar_image_file_public_id);
        Storage::disk('atlas_files')->assertMissing($storedPath);
    }

    public function test_rejected_profile_avatar_image_is_not_activated(): void
    {
        Storage::fake('atlas_files');
        Config::set('atlas.files.disk', 'atlas_files');
        Config::set('atlas.files.fake_scanner_result', 'infected');
        Config::set('atlas.files.allowed_extensions', ['png', 'jpg', 'jpeg', 'webp']);
        Config::set('atlas.files.allowed_mime_types', ['image/png', 'image/jpeg', 'image/webp']);

        [$user, $team] = $this->userWithTeam();
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE);
        $this->assignDirectPermissionInTeam($user, $team, UserPermissionCatalog::USERS_PROFILE_AVATAR_UPDATE);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->post('/user/avatar', [
                'avatar_color' => '#fef3c7',
                'avatar_image' => self::fakePngAvatar(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('avatar_image');

        $user->refresh();
        self::assertNull($user->avatar_image_file_public_id);

        $file = DB::table(FilesDatabaseTable::FILE_OBJECTS)->where('original_name', 'avatar.png')->first();
        self::assertIsObject($file);
        self::assertSame('infected', $file->scan_state);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'User Profile Team',
            'slug' => 'user-profile-team',
            'is_active' => true,
        ]);

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    private function assignDirectPermissionInTeam(User $user, Team $team, string $permissionName): void
    {
        $permission = Permission::query()->where('name', $permissionName)->firstOrFail();

        DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
            'permission_id' => $permission->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    private function enableTracking(User $user, Team $team): void
    {
        $assignmentId = DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->value('id');

        self::assertIsNumeric($assignmentId);

        $this->app->make(UserTeamTrackingSettings::class)->setEnabledForAssignment((int) $assignmentId, true);
    }

    private function activateTimeTracking(Team $team): void
    {
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Global,
            enabled: true,
            reason: 'Feature test setup',
            source: ModuleActivationSource::Manual,
        ));
        $this->app->make(ModuleActivationService::class)->change(new ModuleActivationChange(
            moduleKey: 'time_tracking',
            scope: ModuleActivationScope::Team,
            enabled: true,
            reason: 'Feature test setup',
            teamId: $team->id,
            source: ModuleActivationSource::Manual,
        ));
    }

    private function numericId(mixed $value): int
    {
        self::assertTrue(is_int($value) || is_numeric($value));

        return (int) $value;
    }

    private static function fakePngAvatar(): UploadedFile
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true,
        );

        self::assertIsString($content);

        return UploadedFile::fake()->createWithContent('avatar.png', $content);
    }

    private static function stringValue(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
