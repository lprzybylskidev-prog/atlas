<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationInbox;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Notifications\Application\UserNotificationEmailPreferences;
use App\Modules\Core\Notifications\Infrastructure\Persistence\DatabaseNotificationStore;
use App\Modules\Core\Notifications\Infrastructure\Persistence\TableNames\NotificationsDatabaseTable;
use App\Modules\Core\Notifications\Presentation\Jobs\DeliverNotification;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Mail\AtlasBilingualMail;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
                ->where('table.capabilities.savedViews', true)
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
        $extraAddressId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->insertGetId([
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
        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)->insert([
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
        $recipientId = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(NotificationsDatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notifications.public_id', $publicId)
            ->value('notification_recipients.id');

        self::assertIsNumeric($recipientId);
        self::assertSame(
            [$user->email],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads((int) $recipientId), 'email'),
        );

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)->where('id', $extraAddressId)->update([
            'verified_at' => now(),
            'verification_token_hash' => null,
        ]);

        self::assertSame(
            [$user->email, 'extra@example.test'],
            array_column($this->app->make(DatabaseNotificationStore::class)->emailPayloads((int) $recipientId), 'email'),
        );

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
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
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $teamB->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preferences = $this->app->make(UserNotificationEmailPreferences::class);
        $preferences->ensurePrimaryAddressForUser((int) $user->id, (string) $user->email, null, $teamA->id);
        $preferences->ensurePrimaryAddressForUser((int) $user->id, (string) $user->email, null, $teamB->id);

        $teamAAddressId = DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_ADDRESSES)
            ->where('user_id', $user->id)
            ->where('team_id', $teamA->id)
            ->where('primary', true)
            ->value('id');
        self::assertIsNumeric($teamAAddressId);

        DB::table(NotificationsDatabaseTable::NOTIFICATION_EMAIL_PREFERENCES)
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

    public function test_verified_notification_delivery_and_address_verification_use_bilingual_mail(): void
    {
        Queue::fake();
        Mail::fake();

        [$user, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $preferences = $this->app->make(UserNotificationEmailPreferences::class);
        $verifiedAt = $user->getAttribute('email_verified_at');
        $preferences->addAddressForUser(
            (int) $user->id,
            (string) $user->email,
            $verifiedAt instanceof DateTimeInterface ? $verifiedAt : null,
            'additional@example.test',
            (string) $team->public_id,
        );

        Mail::assertSent(AtlasBilingualMail::class, function (AtlasBilingualMail $mail): bool {
            $html = (string) $mail->render();

            return str_contains($html, 'Potwierdź adres e-mail do powiadomień')
                && str_contains($html, 'Verify notification e-mail address');
        });

        Mail::fake();
        $preferences->ensurePrimaryAddressForUser((int) $user->id, (string) $user->email, now(), (int) $team->id);
        $publicId = $this->app->make(NotificationPublisher::class)->publish(new CreateNotification(
            type: 'report_export.available',
            title: 'notifications.exports.available.title',
            body: 'notifications.exports.available.body',
            recipientUserPublicId: (string) $user->public_id,
            teamPublicId: (string) $team->public_id,
            deepLinkUrl: '/user/notifications',
            data: [
                'title_key' => 'notifications.exports.available.title',
                'body_key' => 'notifications.exports.available.body',
                'report_name' => 'Users',
            ],
            emailRequested: true,
        ));

        $this->app->call([new DeliverNotification($this->recipientId($publicId)), 'handle']);

        Mail::assertSent(AtlasBilingualMail::class, function (AtlasBilingualMail $mail): bool {
            $html = (string) $mail->render();

            return str_contains($html, 'Eksport jest gotowy')
                && str_contains($html, 'Export is ready');
        });
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
                ->where('summary.total', 1)
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

        $ids = DB::table(NotificationsDatabaseTable::NOTIFICATIONS)
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
        self::assertDatabaseHas(NotificationsDatabaseTable::NOTIFICATIONS, [
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

        self::assertDatabaseHas(NotificationsDatabaseTable::REALTIME_EVENTS, ['event_type' => 'operation.progress']);
        self::assertDatabaseHas(NotificationsDatabaseTable::REALTIME_EVENTS, ['event_type' => 'session.invalidated']);
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

        DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)->update([
            'read_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        DB::table(NotificationsDatabaseTable::NOTIFICATIONS)->where('public_id', $publicId)->update(['created_at' => now()->subDays(120)]);
        DB::table(NotificationsDatabaseTable::REALTIME_EVENTS)->update(['created_at' => now()->subHours(120)]);

        self::assertSame(0, Artisan::call('notifications:prune', [
            '--read-days' => '90',
            '--realtime-hours' => '72',
        ]));

        self::assertDatabaseCount(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS, 0);
        self::assertDatabaseCount(NotificationsDatabaseTable::NOTIFICATIONS, 0);
        self::assertDatabaseCount(NotificationsDatabaseTable::REALTIME_EVENTS, 0);
    }

    public function test_notification_center_requires_workspace_permission(): void
    {
        [$user, $team] = $this->userWithTeam(null);

        $this->actingAs($user)
            ->withSession(['active_team_public_id' => $team->public_id])
            ->get('/user/notifications')
            ->assertForbidden();
    }

    public function test_shell_neutral_saved_views_enforce_owner_team_and_surface_permissions(): void
    {
        [$owner, $team] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        $collaborator = $this->addWorkspaceUserToTeam($team);
        [$outsider, $otherTeam] = $this->userWithTeam(StarterRoleName::WorkspaceAccess->value);
        [$unauthorized, $unauthorizedTeam] = $this->userWithTeam(null);

        $privatePayload = $this->savedViewPayload('Private inbox', 'private');
        $this->actingAs($owner)->withSession(['active_team_public_id' => $team->public_id])
            ->post('/table-views', $privatePayload)
            ->assertRedirect();
        $privateId = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('name', 'Private inbox')->value('public_id');
        self::assertIsString($privateId);

        $this->actingAs($collaborator)->withSession(['active_team_public_id' => $team->public_id])
            ->patch('/table-views/'.$privateId, ['name' => 'Not mine', 'state' => $privatePayload['state']])
            ->assertNotFound();

        $teamPayload = $this->savedViewPayload('Team inbox', 'team');
        $this->actingAs($owner)->withSession(['active_team_public_id' => $team->public_id])
            ->post('/table-views', $teamPayload)
            ->assertRedirect();
        $teamViewId = DB::table(DatabaseTable::TABLE_SAVED_VIEWS)->where('name', 'Team inbox')->value('public_id');
        self::assertIsString($teamViewId);

        $this->actingAs($collaborator)->withSession(['active_team_public_id' => $team->public_id])
            ->patch('/table-views/'.$teamViewId, ['name' => 'Shared inbox', 'state' => $teamPayload['state']])
            ->assertRedirect();
        $this->actingAs($collaborator)->withSession(['active_team_public_id' => $team->public_id])
            ->post('/table-views/'.$teamViewId.'/default')
            ->assertRedirect();
        $this->actingAs($collaborator)->withSession(['active_team_public_id' => $team->public_id])
            ->post('/table-views/'.$teamViewId.'/copy', ['name' => 'My inbox copy', 'type' => 'private'])
            ->assertRedirect();

        $this->actingAs($outsider)->withSession(['active_team_public_id' => $otherTeam->public_id])
            ->delete('/table-views/'.$teamViewId)
            ->assertNotFound();
        $this->actingAs($unauthorized)->withSession(['active_team_public_id' => $unauthorizedTeam->public_id])
            ->post('/table-views', $this->savedViewPayload('Forbidden inbox', 'private'))
            ->assertForbidden();

        self::assertDatabaseHas(DatabaseTable::TABLE_SAVED_VIEW_DEFAULTS, [
            'user_id' => $collaborator->id,
            'team_id' => $team->id,
        ]);
        self::assertDatabaseHas(DatabaseTable::TABLE_SAVED_VIEWS, [
            'name' => 'My inbox copy',
            'type' => 'private',
            'owner_user_id' => $collaborator->id,
        ]);
        foreach (['table_saved_view.created', 'table_saved_view.updated', 'table_saved_view.default_set'] as $action) {
            self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
                'module' => 'shared',
                'action' => $action,
                'result' => 'succeeded',
                'source' => 'ui',
            ]);
        }

        $this->actingAs($collaborator)->withSession(['active_team_public_id' => $team->public_id])
            ->delete('/table-views/'.$teamViewId)
            ->assertRedirect();
        self::assertDatabaseHas(AuditDatabaseTable::AUDIT_EVENTS, [
            'module' => 'shared',
            'action' => 'table_saved_view.deleted',
            'result' => 'succeeded',
            'source' => 'ui',
        ]);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function userWithTeam(?string $roleName): array
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create();
        $team = Team::query()->create(['name' => 'Notifications Team']);

        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($roleName !== null) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
                'role_id' => $role->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }

        return [$user, $team];
    }

    private function addWorkspaceUserToTeam(Team $team): User
    {
        $user = User::factory()->create();
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = Role::query()->where('name', StarterRoleName::WorkspaceAccess->value)->value('id');
        self::assertIsNumeric($roleId);
        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => (int) $roleId,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);

        return $user;
    }

    /** @return array{table_key: string, name: string, type: string, state: array<string, mixed>} */
    private function savedViewPayload(string $name, string $type): array
    {
        return [
            'table_key' => 'notifications',
            'name' => $name,
            'type' => $type,
            'state' => [
                'sort' => 'createdAt',
                'direction' => 'desc',
                'search' => '',
                'columns' => ['title', 'severity', 'createdAt'],
                'columnOrder' => ['title', 'severity', 'createdAt'],
                'filters' => ['status' => 'unread'],
            ],
        ];
    }

    private function recipientId(string $notificationPublicId): int
    {
        $id = DB::table(NotificationsDatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(NotificationsDatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notifications.public_id', $notificationPublicId)
            ->value('notification_recipients.id');

        self::assertIsNumeric($id);

        return (int) $id;
    }
}
