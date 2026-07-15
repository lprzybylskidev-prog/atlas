<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Modules\Contributions\ModuleBreadcrumbDefinition;
use App\Shared\Application\Modules\Contributions\ModuleFrontendEntrypoint;
use App\Shared\Application\Modules\Contributions\ModuleHealthCheckDefinition;
use App\Shared\Application\Modules\Contributions\ModuleMenuItem;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;
use App\Shared\Application\Modules\Contributions\ModuleScheduledTask;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModuleContributionContractsTest extends TestCase
{
    public function test_it_defines_typed_module_contribution_values(): void
    {
        $menuItem = new ModuleMenuItem('identity.users', 'Users', 'identity.users.index', 'identity.users.view');
        $permission = new ModulePermissionDefinition('identity.users.view', 'View users.');
        $breadcrumb = new ModuleBreadcrumbDefinition('identity.users.index', 'identity.users.index', 'dashboard');
        $healthCheck = new ModuleHealthCheckDefinition('identity.database', 'Identity database connectivity.');
        $scheduledTask = new ModuleScheduledTask('identity.cleanup', 'identity:cleanup', 'daily');
        $entrypoint = new ModuleFrontendEntrypoint('login', 'Auth/Login');

        self::assertSame('identity.users', $menuItem->key);
        self::assertTrue($permission->teamScoped);
        self::assertSame('dashboard', $breadcrumb->parentName);
        self::assertTrue($healthCheck->readinessAffects);
        self::assertTrue($scheduledTask->requiresActiveModule);
        self::assertSame('Auth/Login', $entrypoint->component);
    }

    public function test_it_rejects_empty_contribution_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModulePermissionDefinition('', 'View users.');
    }
}
