<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class StarterRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_roles_are_created_from_registered_permission_catalogs(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $administrator = Role::query()
            ->where('name', StarterRoleName::Administrator->value)
            ->firstOrFail();

        self::assertDatabaseHas('roles', ['name' => StarterRoleName::User->value]);
        self::assertDatabaseHas('roles', ['name' => StarterRoleName::Manager->value]);
        self::assertDatabaseHas('roles', ['name' => StarterRoleName::Administrator->value]);
        self::assertCount(
            count($this->app->make(PermissionCatalogRegistry::class)->names()),
            $administrator->permissions,
        );
    }

    public function test_existing_roles_are_not_silently_updated_when_permissions_change(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $administrator = Role::query()
            ->where('name', StarterRoleName::Administrator->value)
            ->firstOrFail();
        $originalCount = $administrator->permissions()->count();

        self::assertDatabaseMissing('permissions', ['name' => 'future.permission']);

        Role::query()
            ->where('name', StarterRoleName::Administrator->value)
            ->update(['updated_at' => now()->subDay()]);

        $this->app->make(InstallStarterRoles::class)->handle();

        $administrator->refresh();

        self::assertSame($originalCount, $administrator->permissions()->count());
    }
}
