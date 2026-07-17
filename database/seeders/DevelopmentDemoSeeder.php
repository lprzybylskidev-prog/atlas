<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Packages\ApplyOnboardingPackageToUser;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Files\Application\DTOs\MalwareScanResult;
use App\Modules\Core\Files\Application\Enums\FileScanState;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Files\Infrastructure\Persistence\DatabaseFileStorage;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\DTOs\BootstrapTeam;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $this->seedManagerRelationships((string) $admin->public_id, $teams);
        $this->seedNotifications((string) $admin->public_id, $teams[0]->publicId);
        $this->seedFiles((int) $admin->id, $teams[0]->publicId);
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

    private function seedFiles(int $adminUserId, string $teamPublicId): void
    {
        if (DB::table(DatabaseTable::FILE_OBJECTS)->where('original_name', 'demo-clean-payment-confirmation.txt')->exists()) {
            return;
        }

        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($teamId)) {
            return;
        }

        $storage = app(FileStorage::class);
        $files = app(DatabaseFileStorage::class);

        $clean = $storage->storeUpload(
            $this->demoUpload('demo-clean-payment-confirmation.txt', "Payment confirmation\nStatus: accepted\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.clean'],
        );
        $files->recordScanResult($this->fileObjectId($clean->publicId), new MalwareScanResult(
            provider: 'demo',
            result: FileScanState::Clean,
            checksumSha256: $clean->checksumSha256,
            scannedAt: CarbonImmutable::now('UTC'),
            engineVersion: 'demo-engine',
            signatureVersion: 'demo-signatures-2026-07',
        ));

        $storage->storeUpload(
            $this->demoUpload('demo-duplicate-payment-confirmation.txt', "Payment confirmation\nStatus: accepted\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.duplicate'],
        );

        $storage->storeUpload(
            $this->demoUpload('demo-pending-large-import-attachment.txt', "Large import attachment waiting for scan\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.pending'],
        );

        $infected = $storage->storeUpload(
            $this->demoUpload('demo-infected-suspicious-attachment.txt', "Suspicious attachment demo\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.infected'],
        );
        $files->recordScanResult($this->fileObjectId($infected->publicId), new MalwareScanResult(
            provider: 'demo',
            result: FileScanState::Infected,
            checksumSha256: $infected->checksumSha256,
            scannedAt: CarbonImmutable::now('UTC'),
            engineVersion: 'demo-engine',
            signatureVersion: 'demo-signatures-2026-07',
            threatName: 'Demo.Eicar.Signature',
        ));

        $failed = $storage->storeUpload(
            $this->demoUpload('demo-failed-scanner-timeout.txt', "Scanner timeout demo\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.failed'],
        );
        $files->recordScanResult($this->fileObjectId($failed->publicId), new MalwareScanResult(
            provider: 'demo',
            result: FileScanState::Failed,
            checksumSha256: $failed->checksumSha256,
            scannedAt: CarbonImmutable::now('UTC'),
            engineVersion: 'demo-engine',
            signatureVersion: 'demo-signatures-2026-07',
            metadata: ['reason' => 'scanner_timeout'],
        ));

        $unsupported = $storage->storeUpload(
            $this->demoUpload('demo-unsupported-archive.txt', "Unsupported archive demo\n"),
            $adminUserId,
            $teamId,
            ['demo_key' => 'files.unsupported'],
        );
        $files->recordScanResult($this->fileObjectId($unsupported->publicId), new MalwareScanResult(
            provider: 'demo',
            result: FileScanState::Unsupported,
            checksumSha256: $unsupported->checksumSha256,
            scannedAt: CarbonImmutable::now('UTC'),
            engineVersion: 'demo-engine',
            signatureVersion: 'demo-signatures-2026-07',
            metadata: ['reason' => 'unsupported_container'],
        ));
    }

    private function demoUpload(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fileObjectId(string $publicId): int
    {
        $id = DB::table(DatabaseTable::FILE_OBJECTS)->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * @param  list<BootstrapTeam>  $teams
     */
    private function seedManagerRelationships(string $actorPublicId, array $teams): void
    {
        $this->setHeadManager($teams[0]->publicId, 'demo.copy.north@example.test');
        $this->setHeadManager($teams[1]->publicId, 'demo.copy.south@example.test');

        $this->seedRelationship($actorPublicId, $teams[0]->publicId, 'demo.copy.north@example.test', 'demo.user.04@example.test');
        $this->seedRelationship($actorPublicId, $teams[0]->publicId, 'demo.user.04@example.test', 'demo.user.01@example.test');
        $this->seedRelationship($actorPublicId, $teams[0]->publicId, 'demo.user.04@example.test', 'demo.user.07@example.test');
        $this->seedRelationship($actorPublicId, $teams[1]->publicId, 'demo.copy.south@example.test', 'demo.user.02@example.test');
        $this->seedRelationship($actorPublicId, $teams[1]->publicId, 'demo.copy.south@example.test', 'demo.user.05@example.test');
        $this->seedRelationship($actorPublicId, $teams[2]->publicId, 'demo.copy.backoffice@example.test', 'demo.multi.team@example.test');
    }

    private function setHeadManager(string $teamPublicId, string $email): void
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');
        $userId = DB::table(DatabaseTable::USERS)->where('email', $email)->value('id');

        if (! is_int($teamId) || ! is_int($userId)) {
            return;
        }

        DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->whereNull('valid_to')
            ->update([
                'is_head_manager' => true,
                'updated_at' => now(),
            ]);
    }

    private function seedRelationship(string $actorPublicId, string $teamPublicId, string $managerEmail, string $reportEmail): void
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');
        $managerId = DB::table(DatabaseTable::USERS)->where('email', $managerEmail)->value('id');
        $reportId = DB::table(DatabaseTable::USERS)->where('email', $reportEmail)->value('id');
        $actorId = DB::table(DatabaseTable::USERS)->where('public_id', $actorPublicId)->value('id');

        if (! is_int($teamId) || ! is_int($managerId) || ! is_int($reportId)) {
            return;
        }

        if (DB::table(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS)
            ->where('team_id', $teamId)
            ->where('manager_user_id', $managerId)
            ->where('report_user_id', $reportId)
            ->whereNull('valid_to')
            ->exists()) {
            return;
        }

        DB::table(DatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->insert([
            'public_id' => (string) Str::ulid(),
            'team_id' => $teamId,
            'manager_user_id' => $managerId,
            'report_user_id' => $reportId,
            'valid_from' => now(),
            'valid_to' => null,
            'created_by_user_id' => is_int($actorId) ? $actorId : null,
            'ended_by_user_id' => null,
            'reason' => 'Development demo hierarchy.',
            'end_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
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
