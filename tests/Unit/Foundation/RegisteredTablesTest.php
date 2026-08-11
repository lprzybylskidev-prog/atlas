<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Tables\RegisteredTables;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RegisteredTablesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function nonAdminSavedViewTables(): iterable
    {
        yield 'notifications' => [RegisteredTables::NOTIFICATIONS, 'users.notifications.index'];
        yield 'user report' => [RegisteredTables::TIME_TRACKING_USER_WORK_TIME_DAILY, 'users.work-time'];
        yield 'manager work-time summary' => [RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_DAILY, 'manager.work-time.summary.index'];
        yield 'manager operations' => [RegisteredTables::TIME_TRACKING_MANAGER_OPERATIONS_WORK_SESSIONS, 'manager.work-time.work-sessions.index'];
    }

    #[DataProvider('nonAdminSavedViewTables')]
    public function test_non_admin_saved_view_tables_are_explicitly_registered(string $tableKey, string $permission): void
    {
        self::assertSame($tableKey, RegisteredTables::get($tableKey)->key);
        self::assertSame($permission, RegisteredTables::savedViewAccess($tableKey)->permission);
        self::assertTrue(RegisteredTables::savedViewAccess($tableKey)->teamSharingAllowed);
    }

    public function test_every_registered_table_has_a_definition_and_saved_view_access_contract(): void
    {
        foreach (RegisteredTables::keys() as $key) {
            self::assertSame($key, RegisteredTables::get($key)->key);
            self::assertNotSame('', RegisteredTables::savedViewAccess($key)->permission);
        }
    }

    public function test_shared_frontend_and_routes_do_not_reference_admin_saved_view_endpoints(): void
    {
        $dataTable = file_get_contents(resource_path('js/Components/DataTable.vue'));
        $applicationRoutes = file_get_contents(base_path('routes/web/application.php'));
        $adminRoutes = file_get_contents(base_path('routes/web/admin.php'));

        self::assertIsString($dataTable);
        self::assertIsString($applicationRoutes);
        self::assertIsString($adminRoutes);
        self::assertStringNotContainsString('/admin/table-views', $dataTable);
        self::assertStringContainsString("'/table-views'", $dataTable);
        self::assertStringContainsString("'/table-views'", $applicationRoutes);
        self::assertStringNotContainsString('table-views', $adminRoutes);
        self::assertFileDoesNotExist(app_path('Shared/Application/Tables/AdminTableDefinitions.php'));
    }
}
