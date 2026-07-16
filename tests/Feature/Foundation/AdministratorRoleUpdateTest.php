<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class AdministratorRoleUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_role_update_shows_diff_and_requires_reason_to_apply(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();
        $permission = Permission::query()->where('name', CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS)->firstOrFail();
        $role->revokePermissionTo($permission);

        $previewExitCode = Artisan::call('authorization:update-administrator-role');

        self::assertSame(Command::SUCCESS, $previewExitCode);
        self::assertStringContainsString(CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS, Artisan::output());

        $missingReasonExitCode = Artisan::call('authorization:update-administrator-role', [
            '--apply' => true,
        ]);

        self::assertSame(Command::FAILURE, $missingReasonExitCode);

        $appliedExitCode = Artisan::call('authorization:update-administrator-role', [
            '--apply' => true,
            '--reason' => 'Sync administrator catalog after deployment',
        ]);

        self::assertSame(Command::SUCCESS, $appliedExitCode);
        self::assertTrue($role->fresh()?->hasPermissionTo(CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS));
        self::assertDatabaseHas('security_audit_events', [
            'module' => 'authorization',
            'action' => 'authorization.administrator_role_update',
            'result' => 'succeeded',
            'reason' => 'Sync administrator catalog after deployment',
        ]);
    }
}
