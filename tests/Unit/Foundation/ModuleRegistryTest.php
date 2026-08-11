<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Modules\Core\Audit\AuditModule;
use App\Modules\Core\Authorization\AuthorizationModule;
use App\Modules\Core\Exports\ExportsModule;
use App\Modules\Core\Files\FilesModule;
use App\Modules\Core\Health\HealthModule;
use App\Modules\Core\Identity\IdentityModule;
use App\Modules\Core\Notifications\NotificationsModule;
use App\Modules\Core\Privacy\PrivacyModule;
use App\Modules\Core\Settings\SettingsModule;
use App\Modules\Core\Teams\TeamsModule;
use App\Modules\Core\Users\UsersModule;
use App\Modules\Optional\FeatureFlags\FeatureFlagsModule;
use App\Modules\Optional\Imports\ImportsModule;
use App\Modules\Optional\Integrations\IntegrationsModule;
use App\Modules\Optional\ManagedProcesses\ManagedProcessesModule;
use App\Modules\Optional\Reports\ReportsModule;
use App\Modules\Optional\Search\SearchModule;
use App\Modules\Optional\TimeTracking\TimeTrackingModule;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Exceptions\DuplicateModuleKey;
use App\Shared\Application\Modules\Exceptions\InvalidModuleDefinition;
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

    public function test_it_allows_missing_optional_dependencies(): void
    {
        $registry = new ModuleRegistry([
            new FakeModuleDefinition('exports', optionalDependencies: ['managed_processes']),
        ]);

        self::assertTrue($registry->has(new ModuleKey('exports')));
        self::assertFalse($registry->has(new ModuleKey('managed_processes')));
    }

    public function test_all_core_modules_form_a_valid_reduced_registry_without_optional_modules(): void
    {
        $registry = new ModuleRegistry([
            new IdentityModule,
            new AuthorizationModule,
            new TeamsModule,
            new UsersModule,
            new AuditModule,
            new SettingsModule,
            new NotificationsModule,
            new HealthModule,
            new FilesModule,
            new ExportsModule,
            new PrivacyModule,
        ]);

        self::assertCount(11, $registry->all());
        self::assertFalse($registry->has(new ModuleKey('managed_processes')));
        self::assertCount(11, $registry->startupOrder());
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

    public function test_it_rejects_dependency_cycles_through_deployed_optional_dependencies(): void
    {
        $this->expectException(ModuleDependencyCycle::class);
        $this->expectExceptionMessage('Module dependency cycle detected including module [alpha].');

        new ModuleRegistry([
            new FakeModuleDefinition('alpha', optionalDependencies: ['beta']),
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

    public function test_it_orders_deployed_optional_dependencies_before_consumers(): void
    {
        $registry = new ModuleRegistry([
            new FakeModuleDefinition('exports', optionalDependencies: ['managed_processes']),
            new FakeModuleDefinition('managed_processes'),
            new FakeModuleDefinition('identity'),
        ]);

        self::assertSame(
            ['managed_processes', 'exports', 'identity'],
            array_map(
                static fn (ModuleDefinition $module): string => $module->key()->value,
                $registry->startupOrder(),
            ),
        );
    }

    public function test_it_rejects_duplicate_self_and_overlapping_dependencies(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [cases]: duplicate required dependencies [identity].');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', requiredDependencies: ['identity', 'identity']),
            new FakeModuleDefinition('identity'),
        ]);
    }

    public function test_it_rejects_self_dependencies(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [cases]: a module cannot depend on itself.');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', requiredDependencies: ['cases']),
        ]);
    }

    public function test_it_rejects_dependencies_that_are_both_required_and_optional(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [cases]: dependencies cannot be both required and optional [identity].');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', requiredDependencies: ['identity'], optionalDependencies: ['identity']),
            new FakeModuleDefinition('identity'),
        ]);
    }

    public function test_it_rejects_core_modules_that_require_optional_modules(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [exports]: Core modules cannot require Optional module [managed_processes].');

        new ModuleRegistry([
            new FakeModuleDefinition('exports', category: ModuleCategory::Core, requiredDependencies: ['managed_processes']),
            new FakeModuleDefinition('managed_processes', category: ModuleCategory::Optional),
        ]);
    }

    public function test_it_rejects_missing_service_provider_classes(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [cases]: service provider class [App\\Missing\\CasesServiceProvider] does not exist.');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', serviceProvider: 'App\\Missing\\CasesServiceProvider'),
        ]);
    }

    public function test_it_rejects_empty_or_duplicate_metadata_values(): void
    {
        $this->expectException(InvalidModuleDefinition::class);
        $this->expectExceptionMessage('Invalid module definition for [cases]: health checks contain duplicate values [postgresql].');

        new ModuleRegistry([
            new FakeModuleDefinition('cases', healthChecks: ['postgresql', 'postgresql']),
        ]);
    }

    public function test_it_exposes_registered_modules_by_key(): void
    {
        $identity = new FakeModuleDefinition('identity');
        $registry = new ModuleRegistry([$identity]);

        self::assertTrue($registry->has(new ModuleKey('identity')));
        self::assertSame($identity, $registry->get(new ModuleKey('identity')));
        self::assertSame([$identity], $registry->all());
    }

    public function test_it_exposes_required_reverse_dependencies_for_safe_deactivation(): void
    {
        $registry = new ModuleRegistry([
            new FakeModuleDefinition('managed_processes'),
            new FakeModuleDefinition('imports', requiredDependencies: ['managed_processes']),
            new FakeModuleDefinition('exports', optionalDependencies: ['managed_processes']),
        ]);

        self::assertSame(
            ['imports'],
            array_map(
                static fn (ModuleDefinition $module): string => $module->key()->value,
                $registry->requiredDependentsOf(new ModuleKey('managed_processes')),
            ),
        );
    }

    public function test_configured_deployed_modules_are_explicit_module_definitions(): void
    {
        $moduleClasses = $this->configuredModuleClasses();

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
            FeatureFlagsModule::class,
            IntegrationsModule::class,
            ManagedProcessesModule::class,
            ExportsModule::class,
            PrivacyModule::class,
            ImportsModule::class,
            SearchModule::class,
            ReportsModule::class,
            TimeTrackingModule::class,
        ], $moduleClasses);

        foreach ($moduleClasses as $moduleClass) {
            $interfaces = class_implements($moduleClass);

            self::assertIsArray($interfaces);
            self::assertContains(ModuleDefinition::class, $interfaces);
        }
    }

    public function test_configured_deployed_modules_pass_registry_metadata_validation(): void
    {
        $modules = array_map(
            static fn (string $moduleClass): ModuleDefinition => new $moduleClass,
            $this->configuredModuleClasses(),
        );

        $registry = new ModuleRegistry($modules);

        self::assertCount(18, $registry->all());
        self::assertSame([
            'identity',
            'authorization',
            'teams',
            'audit',
            'files',
            'notifications',
            'users',
            'settings',
            'health',
            'feature_flags',
            'integrations',
            'managed_processes',
            'exports',
            'privacy',
            'imports',
            'search',
            'reports',
            'time_tracking',
        ], array_map(
            static fn (ModuleDefinition $module): string => $module->key()->value,
            $registry->startupOrder(),
        ));
    }

    public function test_configured_module_health_checks_are_backed_by_readiness_checks(): void
    {
        $supportedReadinessChecks = [
            'critical-configuration',
            'postgresql',
            'redis',
            'queues',
            'storage',
            'scheduler',
            'meilisearch',
            'clamav',
            'chromium-pdf',
        ];

        foreach ($this->configuredModuleClasses() as $moduleClass) {
            $module = new $moduleClass;

            foreach ($module->healthChecks() as $healthCheck) {
                self::assertContains(
                    $healthCheck,
                    $supportedReadinessChecks,
                    sprintf('Module [%s] declares unsupported readiness check [%s].', $module->key()->value, $healthCheck),
                );
            }
        }
    }

    public function test_configured_metadata_categories_have_executable_semantics(): void
    {
        foreach ($this->configuredModuleClasses() as $moduleClass) {
            $module = new $moduleClass;

            if ($module->category() === ModuleCategory::Core) {
                self::assertFalse($module->supportsGlobalActivation(), sprintf('Core module [%s] cannot advertise mutable global activation.', $module->key()->value));
                self::assertFalse($module->supportsTeamActivation(), sprintf('Core module [%s] cannot advertise mutable team activation.', $module->key()->value));
            }
        }
    }

    /**
     * @return list<class-string<ModuleDefinition>>
     */
    private function configuredModuleClasses(): array
    {
        $configured = require __DIR__.'/../../../config/modules.php';

        self::assertIsArray($configured);
        self::assertArrayHasKey('deployed', $configured);
        self::assertIsArray($configured['deployed']);

        $classes = [];

        foreach ($configured['deployed'] as $moduleClass) {
            self::assertIsString($moduleClass);
            self::assertTrue(is_subclass_of($moduleClass, ModuleDefinition::class));
            $classes[] = $moduleClass;
        }

        return $classes;
    }
}

final readonly class FakeModuleDefinition implements ModuleDefinition
{
    /**
     * @param  list<string>  $requiredDependencies
     * @param  list<string>  $optionalDependencies
     * @param  list<string>  $healthChecks
     */
    public function __construct(
        private string $key,
        private ModuleCategory $category = ModuleCategory::Application,
        private array $requiredDependencies = [],
        private array $optionalDependencies = [],
        private string $serviceProvider = self::class,
        private array $healthChecks = [],
    ) {}

    public function key(): ModuleKey
    {
        return new ModuleKey($this->key);
    }

    public function category(): ModuleCategory
    {
        return $this->category;
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
        return $this->serviceProvider;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function healthChecks(): array
    {
        return $this->healthChecks;
    }
}
