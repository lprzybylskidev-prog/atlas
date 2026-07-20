<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Audit\AuditModule;
use App\Modules\Core\Authorization\AuthorizationModule;
use App\Modules\Core\Files\FilesModule;
use App\Modules\Core\Health\HealthModule;
use App\Modules\Core\Identity\IdentityModule;
use App\Modules\Core\Notifications\NotificationsModule;
use App\Modules\Core\Settings\SettingsModule;
use App\Modules\Core\Teams\TeamsModule;
use App\Modules\Core\Users\UsersModule;
use App\Modules\Optional\Imports\ImportsModule;
use App\Modules\Optional\Integrations\IntegrationsModule;
use App\Modules\Optional\ManagedProcesses\ManagedProcessesModule;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Exceptions\DuplicateModuleKey;
use App\Shared\Application\Modules\Exceptions\MissingRequiredModuleDependency;
use App\Shared\Application\Modules\Exceptions\ModuleDependencyCycle;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    public function test_it_accepts_only_unique_module_keys(): void
    {
        $this->expectException(DuplicateModuleKey::class);
        $this->expectExceptionMessage('Duplicate module key [identity] in deployed module registry.');

        new ModuleRegistry([
            new FakeModuleDefinition('identity'),
            new FakeModuleDefinition('identity'),
        ]);
    }

    public function test_it_rejects_missing_required_dependencies(): void
    {
        $this->expectException(MissingRequiredModuleDependency::class);
        $this->expectExceptionMessage('Module [cases] requires missing deployed dependency [identity].');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', requiredDependencies: ['identity']),
        ]);
    }

    public function test_it_rejects_dependency_cycles(): void
    {
        $this->expectException(ModuleDependencyCycle::class);
        $this->expectExceptionMessage('Module dependency cycle detected including module [alpha].');

        new ModuleRegistry([
            new FakeModuleDefinition('alpha', requiredDependencies: ['beta']),
            new FakeModuleDefinition('beta', requiredDependencies: ['alpha']),
        ]);
    }

    public function test_it_computes_deterministic_startup_order_with_dependencies_first(): void
    {
        $registry = new ModuleRegistry([
            new FakeModuleDefinition('cases', requiredDependencies: ['identity', 'teams']),
            new FakeModuleDefinition('teams', requiredDependencies: ['identity']),
            new FakeModuleDefinition('identity'),
        ]);

        self::assertSame(
            ['identity', 'teams', 'cases'],
            array_map(
                static fn (ModuleDefinition $module): string => $module->key()->value,
                $registry->startupOrder(),
            ),
        );
    }

    public function test_it_exposes_registered_modules_by_key(): void
    {
        $identity = new FakeModuleDefinition('identity');
        $registry = new ModuleRegistry([$identity]);

        self::assertTrue($registry->has(new ModuleKey('identity')));
        self::assertSame($identity, $registry->get(new ModuleKey('identity')));
        self::assertSame([$identity], $registry->all());
    }

    public function test_configured_deployed_modules_are_explicit_module_definitions(): void
    {
        $configured = require __DIR__.'/../../../config/modules.php';

        self::assertIsArray($configured);
        self::assertArrayHasKey('deployed', $configured);
        self::assertIsArray($configured['deployed']);
        self::assertSame([
            IdentityModule::class,
            AuthorizationModule::class,
            TeamsModule::class,
            UsersModule::class,
            AuditModule::class,
            SettingsModule::class,
            NotificationsModule::class,
            HealthModule::class,
            FilesModule::class,
            IntegrationsModule::class,
            ManagedProcessesModule::class,
            ImportsModule::class,
        ], $configured['deployed']);

        foreach ($configured['deployed'] as $moduleClass) {
            $interfaces = class_implements($moduleClass);

            self::assertIsArray($interfaces);
            self::assertContains(ModuleDefinition::class, $interfaces);
        }
    }
}

final readonly class FakeModuleDefinition implements ModuleDefinition
{
    /**
     * @param  list<string>  $requiredDependencies
     * @param  list<string>  $optionalDependencies
     */
    public function __construct(
        private string $key,
        private array $requiredDependencies = [],
        private array $optionalDependencies = [],
    ) {}

    public function key(): ModuleKey
    {
        return new ModuleKey($this->key);
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Application;
    }

    public function requiredDependencies(): array
    {
        return array_map(
            static fn (string $dependency): ModuleKey => new ModuleKey($dependency),
            $this->requiredDependencies,
        );
    }

    public function optionalDependencies(): array
    {
        return array_map(
            static fn (string $dependency): ModuleKey => new ModuleKey($dependency),
            $this->optionalDependencies,
        );
    }

    public function serviceProvider(): string
    {
        return self::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function integrations(): array
    {
        return [];
    }

    public function healthChecks(): array
    {
        return [];
    }

    public function frontendEntrypoints(): array
    {
        return [];
    }
}
