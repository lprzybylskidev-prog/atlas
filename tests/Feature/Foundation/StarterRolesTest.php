<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Shared\Application\Calendar\Permissions\CalendarPermissionNames;
use App\Shared\Application\Chat\Permissions\ChatPermissionNames;
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

        self::assertDatabaseHas(AuthorizationDatabaseTable::ROLES, ['name' => StarterRoleName::WorkspaceAccess->value]);
        self::assertDatabaseHas(AuthorizationDatabaseTable::ROLES, ['name' => StarterRoleName::TeamManagersRead->value]);
        self::assertDatabaseHas(AuthorizationDatabaseTable::ROLES, ['name' => StarterRoleName::Administrator->value]);
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

        self::assertDatabaseMissing(AuthorizationDatabaseTable::PERMISSIONS, ['name' => 'future.permission']);

        Role::query()
            ->where('name', StarterRoleName::Administrator->value)
            ->update(['updated_at' => now()->subDay()]);

        $this->app->make(InstallStarterRoles::class)->handle();

        $administrator->refresh();

        self::assertSame($originalCount, $administrator->permissions()->count());
    }

    public function test_communication_starter_roles_separate_standard_host_and_content_free_operations(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $workspace = Role::query()->where('name', StarterRoleName::WorkspaceAccess->value)->firstOrFail();
        $standard = Role::query()->where('name', StarterRoleName::CommunicationAccess->value)->firstOrFail();
        $host = Role::query()->where('name', StarterRoleName::CommunicationMeetingHost->value)->firstOrFail();
        $operations = Role::query()->where('name', StarterRoleName::CommunicationOperations->value)->firstOrFail();

        self::assertTrue($workspace->hasPermissionTo(CalendarPermissionNames::INDEX));
        self::assertTrue($workspace->hasPermissionTo(ChatPermissionNames::INDEX));
        self::assertTrue($standard->hasPermissionTo(ChatPermissionNames::DIRECT_CONVERSATION_STORE));
        self::assertFalse($standard->hasPermissionTo(ChatPermissionNames::MEETING_MODERATE));
        self::assertTrue($host->hasPermissionTo(ChatPermissionNames::MEETING_MODERATE));
        self::assertTrue($host->hasPermissionTo(ChatPermissionNames::RECORDING_MANAGE));
        self::assertTrue($operations->hasPermissionTo(ChatPermissionNames::INDEX));
        self::assertTrue($operations->hasPermissionTo(ChatPermissionNames::ADMIN_OPERATIONS_INDEX));
        self::assertFalse($operations->hasPermissionTo(ChatPermissionNames::RECORDING_SHOW));
        self::assertFalse($operations->hasPermissionTo(ChatPermissionNames::TRANSCRIPTION_SHOW));
    }
}
