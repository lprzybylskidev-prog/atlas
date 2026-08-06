<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Notifications\Application\UserNotificationEmailPreferences;
use App\Modules\Core\Notifications\Infrastructure\Persistence\DatabaseNotificationStore;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class NotificationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_publisher_creates_inbox_records_and_read_state(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        $publicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'test.notification',
            title: 'Test notification',
            body: 'Visible only in the recipient inbox.',
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            deepLinkUrl: '/user/notifications',
        ));

        $inbox = $this->app->make(NotificationInbox::class);

        self::assertSame(1, $inbox->unreadCount((string) $user->public_id, (string) $team->public_id));
        self::assertCount(1, $inbox->latestForUser((string) $user->public_id, (string) $team->public_id, 10));

        $inbox->markRead((string) $user->public_id, $publicId);

        self::assertSame(0, $inbox->unreadCount((string) $user->public_id, (string) $team->public_id));
    }

    public function test_user_can_view_own_notification_center_and_latest_dropdown_payload(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'test.notification',
            title: 'Dropdown notification',
            body: 'Latest notification body.',
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            deepLinkUrl: '/user/notifications',
        ));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Notifications/Index')
                ->where('table.key', 'notifications')
                ->where('table.state.filters.status', 'all')
                ->where('summary.total', 1)
                ->where('summary.visible', 1)
                ->where('summary.unread', 1)
                ->where('filterOptions.severities.0', 'info')
                ->where('filterOptions.types.0', 'test.notification')
                ->has('notificationRows', 1)
                ->where('notificationRows.0.title', 'Dropdown notification')
                ->where('notificationRows.0.scope', 'team')
                ->where('notificationRows.0.scopeLabel', 'Zespół')
                ->where('notificationRows.0.read', false)
                ->where('notificationRows.0.createdAt', fn (string $value): bool => $value !== '')
                ->where('notifications.unreadCount', 1)
                ->has('notifications.latest', 1)
                ->where('notifications.latest.0.title', 'Dropdown notification'));
    }

    public function test_notification_center_localizes_keyed_notification_text(): void
    {
        Queue::fake();
        app()->setLocale('pl');

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'report_export.available',
            title: 'notifications.exports.available.title',
            body: 'notifications.exports.available.body',
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            deepLinkUrl: '/exports/01J00000000000000000000AAA/download',
            data: [
                'title_key' => 'notifications.exports.available.title',
                'body_key' => 'notifications.exports.available.body',
                'report_name' => 'Użytkownicy',
            ],
        ));

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('notificationRows.0.title', 'Eksport jest gotowy')
                ->where('notificationRows.0.body', 'Eksport Użytkownicy jest gotowy do pobrania.')
                ->where('notifications.latest.0.title', 'Eksport jest gotowy')
                ->where('notifications.latest.0.body', 'Eksport Użytkownicy jest gotowy do pobrania.'));
    }

    public function test_email_delivery_uses_verified_addresses_and_per_address_type_preferences(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $this->app->make(UserNotificationEmailPreferences::class)->ensurePrimaryAddressForUser(
            (int) $user->id,
            (string) $user->email,
            null,
            $team->id,
        );
        $extraAddressId = DB::table(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email' => 'extra@example.test',
            'primary' => false,
            'verified_at' => null,
            'verification_token_hash' => 'pending',
            'verification_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)->insert([
            'notification_email_address_id' => $extraAddressId,
            'team_id' => $team->id,
            'notification_type' => 'report_export.available',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'report_export.available',
            title: 'Export ready',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            emailRequested: true,
        ));
        $recipientId = DB::table(DatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(DatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notifications.public_id', $publicId)
            ->value('notification_recipients.id');

        self::assertIsNumeric($recipientId);
        self::assertSame(
            [$user->email],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads((int) $recipientId), 'email'),
        );

        DB::table(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->where('id', $extraAddressId)->update([
            'verified_at' => now(),
            'verification_token_hash' => null,
        ]);

        self::assertSame(
            [$user->email, 'extra@example.test'],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads((int) $recipientId), 'email'),
        );

        DB::table(DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
            ->where('notification_email_address_id', $extraAddressId)
            ->where('notification_type', 'report_export.available')
            ->update(['enabled' => false]);

        self::assertSame(
            [$user->email],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads((int) $recipientId), 'email'),
        );
    }

    public function test_email_delivery_resolves_preferences_for_the_notification_team_context(): void
    {
        Queue::fake();

        [$user, $teamA] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $teamB = Team::query()->create(['name' => 'Notifications Team B']);
        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $teamB->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preferences = $this->app->make(UserNotificationEmailPreferences::class);
        $preferences->ensurePrimaryAddressForUser((int) $user->id, (string) $user->email, null, $teamA->id);
        $preferences->ensurePrimaryAddressForUser((int) $user->id, (string) $user->email, null, $teamB->id);

        $teamAAddressId = DB::table(DatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $user->id)
            ->where('team_id', $teamA->id)
            ->where('primary', true)
            ->value('id');
        self::assertIsNumeric($teamAAddressId);

        DB::table(DatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
            ->where('notification_email_address_id', (int) $teamAAddressId)
            ->where('team_id', $teamA->id)
            ->where('notification_type', 'report_export.available')
            ->update(['enabled' => false]);

        $teamAPublicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'report_export.available',
            title: 'Team A export ready',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $teamA->public_id,
            emailRequested: true,
        ));
        $teamBPublicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'report_export.available',
            title: 'Team B export ready',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $teamB->public_id,
            emailRequested: true,
        ));

        $teamARecipientId = $this->recipientId($teamAPublicId);
        $teamBRecipientId = $this->recipientId($teamBPublicId);

        self::assertSame([], $this->app->make(DatabaseNotificationStore::class)->emailPayloads($teamARecipientId));
        self::assertSame(
            [$user->email],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads($teamBRecipientId), 'email'),
        );
    }

    public function test_notification_center_applies_status_and_severity_filters(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $publisher = $this->app->make(NotificationPublisher::class);
        $readId = $publisher->publish(new CreateNotification(
            type: 'test.notification',
            title: 'Read warning notification',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            severity: 'warning',
        ));
        $publisher->publish(new CreateNotification(
            type: 'test.notification',
            title: 'Unread info notification',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            severity: 'info',
        ));
        $this->app->make(NotificationInbox::class)->markRead((string) $user->public_id, $readId);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/notifications?status=unread&severity=info')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 2)
                ->where('summary.visible', 1)
                ->where('summary.unread', 1)
                ->where('table.state.filters.status', 'unread')
                ->where('table.state.filters.severity', 'info')
                ->has('notificationRows', 1)
                ->where('notificationRows.0.title', 'Unread info notification'));
    }

    public function test_user_can_mark_notifications_read_in_bulk(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        foreach (['First notification', 'Second notification'] as $title) {
            $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
                type: 'test.notification',
                title: $title,
                body: null,
                recipientUserPublicId: (string) $user->public_id,
                teamPublicId: (string) $team->public_id,
            ));
        }

        $ids = DB::table(DatabaseTable::NOTIFICATIONS)
            ->orderBy('title')
            ->pluck('public_id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->post('/user/notifications/read', ['notifications' => $ids])
            ->assertRedirect();

        self::assertSame(0, $this->app->make(NotificationInbox::class)->unreadCount((string) $user->public_id, (string) $team->public_id));
    }

    public function test_console_command_can_send_locale_specific_notification(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        $exitCode = Artisan::call('notifications:send', [
            '--email' => $user->email,
            '--team' => (string) $team->public_id,
            '--severity' => 'warning',
            '--title-pl' => 'Ręczne powiadomienie',
            '--body-pl' => 'Treść ręcznego powiadomienia.',
            '--title-en' => 'Manual notification',
            '--body-en' => 'Manual notification body.',
            '--link' => '/user/notifications',
        ]);

        self::assertSame(0, $exitCode);
        self::assertDatabaseHas(DatabaseTable::NOTIFICATIONS, [
            'title' => 'Ręczne powiadomienie',
            'severity' => 'warning',
        ]);
    }

    public function test_realtime_feed_returns_only_authorized_user_and_team_events(): void
    {
        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        [, $otherTeam] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $realtime = $this->app->make(RealtimePublisher::class);

        $visibleEvent = $realtime->publishSystemAlert(
            title: 'Visible alert',
            severity: 'warning',
            userPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        );
        $realtime->publishSystemAlert(
            title: 'Hidden alert',
            severity: 'warning',
            userPublicId: (string) $user->public_id,
            teamPublicId: (string) $otherTeam->public_id,
        );

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->getJson('/realtime/events')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.publicId', $visibleEvent)
            ->assertJsonPath('events.0.eventType', 'system.alert');
    }

    public function test_realtime_command_publishes_progress_and_session_events(): void
    {
        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);

        self::assertSame(0, Artisan::call('realtime:publish', [
            'topic' => 'operation-progress',
            '--user' => (string) $user->public_id,
            '--team' => (string) $team->public_id,
            '--operation-type' => 'report',
            '--operation-id' => 'report-1',
            '--status' => 'running',
            '--progress' => '45',
            '--body' => 'Report is running.',
        ]));

        self::assertSame(0, Artisan::call('realtime:publish', [
            'topic' => 'sessions',
            '--user' => (string) $user->public_id,
            '--team' => (string) $team->public_id,
            '--session' => 'session-1',
        ]));

        self::assertDatabaseHas(DatabaseTable::REALTIME_EVENTS, ['event_type' => 'operation.progress']);
        self::assertDatabaseHas(DatabaseTable::REALTIME_EVENTS, ['event_type' => 'session.invalidated']);
    }

    public function test_notification_prune_removes_old_read_and_realtime_records(): void
    {
        Queue::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $publicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'test.notification',
            title: 'Old notification',
            body: null,
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
        ));

        DB::table(DatabaseTable::NOTIFICATION_RECIPIENTS)->update([
            'read_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        DB::table(DatabaseTable::NOTIFICATIONS)->where('public_id', $publicId)->update(['created_at' => now()->subDays(120)]);
        DB::table(DatabaseTable::REALTIME_EVENTS)->update(['created_at' => now()->subHours(120)]);

        self::assertSame(0, Artisan::call('notifications:prune', [
            '--read-days' => '90',
            '--realtime-hours' => '72',
        ]));

        self::assertDatabaseCount(DatabaseTable::NOTIFICATION_RECIPIENTS, 0);
        self::assertDatabaseCount(DatabaseTable::NOTIFICATIONS, 0);
        self::assertDatabaseCount(DatabaseTable::REALTIME_EVENTS, 0);
    }

    public function test_notification_center_requires_workspace_permission(): void
    {
        [$user, $team] = $this->userWithTeam(null);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/notifications')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(?string $roleName): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Notifications Team']);

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($roleName !== null) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            DB::table(DatabaseTable::MODEL_HAS_ROLES)->insert([
                'role_id' => $role->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }

        return [$user, $team];
    }

    private function recipientId(string $notificationPublicId): int
    {
        $id = DB::table(DatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(DatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notifications.public_id', $notificationPublicId)
            ->value('notification_recipients.id');

        self::assertIsNumeric($id);

        return (int) $id;
    }
}
