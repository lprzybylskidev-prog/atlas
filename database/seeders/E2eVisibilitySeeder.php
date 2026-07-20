<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class E2eVisibilitySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.test';

    public const LIMITED_EMAIL = 'limited@example.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        app(InstallStarterRoles::class)->handle();

        $team = app(BootstrapTeamProvider::class)->provide('E2E Visibility Team');
        $admin = $this->user(self::ADMIN_EMAIL, 'Visibility Admin');
        $limited = $this->user(self::LIMITED_EMAIL, 'Visibility User');

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: (string) $admin->public_id,
            teamPublicId: $team->publicId,
        );

        app(PermissionRoleStore::class)->assignRoleToUserInTeam(
            userPublicId: (string) $limited->public_id,
            teamPublicId: $team->publicId,
            roleName: StarterRoleName::WorkspaceAccess->value,
        );

        $this->auditEvents((string) $admin->public_id, $team->publicId);
    }

    private function user(string $email, string $name): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'first_password_set_at' => now(),
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();

        return $user;
    }

    private function auditEvents(string $adminPublicId, string $teamPublicId): void
    {
        $recorder = app(AuditRecorder::class);

        $recorder->record(new AuditEvent(
            module: 'identity',
            action: 'e2e.audit.alpha',
            result: 'succeeded',
            source: 'e2e',
            actorPublicId: $adminPublicId,
            targetType: 'user',
            targetPublicId: $adminPublicId,
            teamPublicId: $teamPublicId,
            correlationId: 'e2e-alpha',
            security: true,
            securityCategory: SecurityAuditCategory::Authentication,
        ));
        $recorder->record(new AuditEvent(
            module: 'shared',
            action: 'e2e.audit.beta',
            result: 'failed',
            source: 'admin-ui',
            actorPublicId: $adminPublicId,
            targetType: 'table_view',
            targetPublicId: (string) Str::ulid(),
            teamPublicId: $teamPublicId,
            correlationId: 'e2e-beta',
            security: false,
        ));
    }
}
