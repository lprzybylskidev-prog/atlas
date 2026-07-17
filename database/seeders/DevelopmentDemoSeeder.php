<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\ApplyOnboardingPackageToUser;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\DTOs\BootstrapTeam;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentDemoSeeder extends Seeder
{
    public const PREVIEW_EMAIL = 'admin@example.test';

    public const PREVIEW_PASSWORD = 'password';

    public function run(): void
    {
        app(InstallStarterRoles::class)->handle();

        $teams = [
            app(BootstrapTeamProvider::class)->provide('Collections North'),
            app(BootstrapTeamProvider::class)->provide('Collections South'),
            app(BootstrapTeamProvider::class)->provide('Back Office'),
        ];
        $this->upsertOnboardingPackages($teams);

        $admin = User::query()->firstOrNew([
            'email' => self::PREVIEW_EMAIL,
        ]);

        $admin->forceFill([
            'name' => 'Admin',
            'password' => Hash::make(self::PREVIEW_PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
        ])->save();

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: (string) $admin->public_id,
            teamPublicId: $teams[0]->publicId,
        );

        foreach (range(1, 9) as $index) {
            $team = $teams[($index - 1) % count($teams)];
            $package = match ($team->publicId) {
                $teams[0]->publicId => ($index - 1) % 2 === 0 ? 'north.collections.agent' : 'north.collections.team_leader',
                $teams[1]->publicId => ($index - 1) % 2 === 0 ? 'south.collections.agent' : 'south.collections.skip_tracer',
                default => 'back_office.specialist',
            };
            $user = $this->demoUser(sprintf('demo.user.%02d@example.test', $index), fake()->name());

            $this->applyPackageIfMissing($user, $team->publicId, $package, (string) $admin->public_id);
        }

        $northCopySource = $this->demoUser('demo.copy.north@example.test', 'Demo Copy Source North');
        $southCopySource = $this->demoUser('demo.copy.south@example.test', 'Demo Copy Source South');
        $backOfficeCopySource = $this->demoUser('demo.copy.backoffice@example.test', 'Demo Copy Source Back Office');
        $multiTeamUser = $this->demoUser('demo.multi.team@example.test', 'Demo Multi Team User');

        $this->applyPackageIfMissing($northCopySource, $teams[0]->publicId, 'north.collections.team_leader', (string) $admin->public_id);
        $this->applyPackageIfMissing($southCopySource, $teams[1]->publicId, 'south.collections.skip_tracer', (string) $admin->public_id);
        $this->applyPackageIfMissing($backOfficeCopySource, $teams[2]->publicId, 'back_office.specialist', (string) $admin->public_id);
        $this->applyPackageIfMissing($multiTeamUser, $teams[0]->publicId, 'north.collections.agent', (string) $admin->public_id);
        $this->applyPackageIfMissing($multiTeamUser, $teams[2]->publicId, 'back_office.specialist', (string) $admin->public_id);

        $this->seedNotifications((string) $admin->public_id, $teams[0]->publicId);
    }

    private function demoUser(string $email, string $name): User
    {
        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::PREVIEW_PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
        ])->save();

        return $user;
    }

    private function applyPackageIfMissing(User $user, string $teamPublicId, string $packageName, string $actorPublicId): void
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($teamId)) {
            return;
        }

        if (DB::table(DatabaseTable::USER_ONBOARDING_PACKAGES)
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->exists()) {
            return;
        }

        app(ApplyOnboardingPackageToUser::class)->apply(
            packageName: $packageName,
            userPublicId: (string) $user->public_id,
            teamPublicId: $teamPublicId,
            actorPublicId: $actorPublicId,
            duringUserCreation: true,
        );
    }

    private function seedNotifications(string $userPublicId, string $teamPublicId): void
    {
        if (DB::table(DatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(DatabaseTable::USERS, 'notification_recipients.user_id', '=', 'users.id')
            ->where('users.public_id', $userPublicId)
            ->exists()) {
            return;
        }

        $publisher = app(NotificationPublisher::class);
        $polish = config()->string('app.locale') === 'pl';

        foreach (range(1, 12) as $index) {
            $publisher->publish(new CreateNotification(
                type: 'demo.notification',
                title: $polish ? sprintf('Powiadomienie demo %02d', $index) : sprintf('Demo notification %02d', $index),
                body: $this->demoNotificationBody($index, $polish),
                recipientUserPublicId: $userPublicId,
                teamPublicId: $teamPublicId,
                severity: $index % 4 === 0 ? 'warning' : 'info',
                deepLinkUrl: '/notifications',
                data: ['demo_index' => $index],
            ));
        }

        DB::table(DatabaseTable::NOTIFICATION_RECIPIENTS)
            ->join(DatabaseTable::NOTIFICATIONS, 'notification_recipients.notification_id', '=', 'notifications.id')
            ->where('notifications.type', 'demo.notification')
            ->whereIn('notifications.title', $polish
                ? ['Powiadomienie demo 03', 'Powiadomienie demo 06', 'Powiadomienie demo 09']
                : ['Demo notification 03', 'Demo notification 06', 'Demo notification 09'])
            ->update([
                'read_at' => now(),
                'notification_recipients.updated_at' => now(),
            ]);
    }

    private function demoNotificationBody(int $index, bool $polish): string
    {
        if ($index % 3 === 0) {
            return $polish
                ? 'To powiadomienie jest już oznaczone jako przeczytane w skrzynce demo.'
                : 'This notification is already marked as read in the demo inbox.';
        }

        return $polish
            ? 'Przykładowe powiadomienie aplikacyjne dla dropdownu avatara i centrum powiadomień.'
            : 'This is an example in-app notification for the avatar dropdown and notification center.';
    }

    /**
     * @param  list<BootstrapTeam>  $teams
     */
    private function upsertOnboardingPackages(array $teams): void
    {
        $packages = app(OnboardingPackageStore::class);

        $packages->upsert(
            teamPublicId: $teams[0]->publicId,
            name: 'north.collections.agent',
            label: 'North collections agent',
            initialRoleNames: [StarterRoleName::WorkspaceAccess->value],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                UserPermissionCatalog::TEAM_SWITCH,
            ],
        );

        $packages->upsert(
            teamPublicId: $teams[0]->publicId,
            name: 'north.collections.team_leader',
            label: 'North team leader',
            initialRoleNames: [
                StarterRoleName::WorkspaceAccess->value,
                StarterRoleName::TeamManagersRead->value,
            ],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                UserPermissionCatalog::TEAM_SWITCH,
                TeamPermissionNames::MANAGERS_VIEW,
            ],
        );

        $packages->upsert(
            teamPublicId: $teams[1]->publicId,
            name: 'south.collections.agent',
            label: 'South collections agent',
            initialRoleNames: [
                StarterRoleName::WorkspaceAccess->value,
                StarterRoleName::AdminUsersRead->value,
            ],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                UserPermissionCatalog::TEAM_SWITCH,
                UserPermissionCatalog::ADMIN_USERS_INDEX,
            ],
        );

        $packages->upsert(
            teamPublicId: $teams[1]->publicId,
            name: 'south.collections.skip_tracer',
            label: 'South skip tracer',
            initialRoleNames: [
                StarterRoleName::WorkspaceAccess->value,
                StarterRoleName::AdminUsersRead->value,
            ],
            directPermissionNames: [CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                UserPermissionCatalog::TEAM_SWITCH,
                UserPermissionCatalog::ADMIN_USERS_INDEX,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS,
            ],
        );

        $packages->upsert(
            teamPublicId: $teams[2]->publicId,
            name: 'back_office.specialist',
            label: 'Back office specialist',
            initialRoleNames: [
                StarterRoleName::WorkspaceAccess->value,
                StarterRoleName::SystemStatusRead->value,
            ],
            directPermissionNames: [],
            templatePermissionNames: [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                UserPermissionCatalog::TEAM_SWITCH,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS,
                CoreAuthorizationPermissionCatalog::SYSTEM_STATUS_VIEW,
            ],
        );
    }
}
