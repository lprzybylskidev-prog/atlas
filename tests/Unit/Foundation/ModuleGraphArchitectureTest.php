<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\Support\Architecture\PhpModuleReferenceScanner;
use Tests\TestCase;

final class ModuleGraphArchitectureTest extends TestCase
{
    public function test_deployed_module_graph_is_non_empty_registered_and_acyclic(): void
    {
        $modules = $this->deployedModules();
        $registry = new ModuleRegistry($modules);

        self::assertCount(20, $modules);
        self::assertSame(
            array_keys($modules),
            array_map(
                static fn (ModuleDefinition $module): string => $module->key()->value,
                $registry->all(),
            ),
        );
        self::assertCount(20, $registry->startupOrder());
    }

    public function test_real_module_imports_match_declared_dependencies(): void
    {
        $drift = $this->undeclaredModuleImportEdges();

        self::assertSame([], $drift);
    }

    public function test_global_providers_and_middleware_use_only_public_or_presentation_module_surfaces(): void
    {
        $imports = $this->moduleClassImportsOutsideModules([
            base_path('app/Providers'),
            base_path('app/Http/Middleware'),
        ]);

        self::assertNotEmpty($imports, 'The global composition guard must inspect real module imports.');
        self::assertSame([], array_values(array_filter(
            $imports,
            fn (array $import): bool => ! $this->globalModuleSurfaceAllowed($import['class']),
        )));
    }

    public function test_modules_do_not_import_foreign_public_persistence_table_classes(): void
    {
        $imports = $this->foreignPublicPersistenceImports();

        self::assertSame([], $this->foreignPublicPersistenceSummary($imports));
        self::assertCount(0, $imports);
    }

    public function test_modules_do_not_reference_foreign_schema_qualified_table_names(): void
    {
        $references = $this->foreignSchemaQualifiedTableReferences();

        self::assertSame([], $references);
    }

    public function test_public_persistence_catalog_namespace_has_been_removed(): void
    {
        $legacyCatalogs = [];

        foreach ($this->modulePhpFiles() as $file) {
            if (str_contains($file->getPathname(), '/Application/Public/Persistence/')) {
                $legacyCatalogs[] = $this->relativePath($file->getPathname());
            }
        }

        self::assertSame([], $legacyCatalogs);
        self::assertCount(16, $this->publicPersistenceModuleMap());
    }

    public function test_config_bootstrap_and_migrations_reference_only_explicit_module_surfaces(): void
    {
        $violations = [];
        $scanner = new PhpModuleReferenceScanner;
        $files = [
            ...$this->phpFilesIn([base_path('config'), base_path('database/migrations')]),
            new SplFileInfo(base_path('bootstrap/app.php')),
        ];

        foreach ($files as $file) {
            foreach ($scanner->referencesInFile($file->getPathname()) as $class) {
                if (! str_starts_with($class, 'App\\Modules\\')) {
                    continue;
                }

                $relative = $this->relativePath($file->getPathname());
                $allowed = str_ends_with($relative, 'config/modules.php') && str_ends_with($class, 'Module')
                    || str_starts_with($relative, 'config/') && str_contains($class, '\\Infrastructure\\Persistence\\TableNames\\')
                    || str_ends_with($relative, 'config/auth.php') && $class === 'App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User'
                    || str_ends_with($relative, 'config/permission.php') && $class === 'App\\Modules\\Core\\Teams\\Infrastructure\\Persistence\\Team'
                    || $relative === 'bootstrap/app.php' && str_contains($class, '\\Presentation\\')
                    || str_starts_with($relative, 'database/migrations/') && str_contains($class, '\\Infrastructure\\Persistence\\TableNames\\');

                if (! $allowed) {
                    $violations[] = $relative.' -> '.$class;
                }
            }
        }

        sort($violations);

        self::assertSame([], $violations);
    }

    public function test_boundary_guards_reject_mutation_fixtures(): void
    {
        $catalogs = $this->publicPersistenceModuleMap();
        $identityCatalog = array_search('identity', $catalogs, true);

        self::assertIsString($identityCatalog);

        $references = (new PhpModuleReferenceScanner)->referencesInCode(sprintf(
            '<?php $table = %s::USERS;',
            '\\'.$identityCatalog,
        ));

        self::assertContains($identityCatalog, $references);
        self::assertSame('identity', $catalogs[$identityCatalog]);
        self::assertNotSame('audit', $catalogs[$identityCatalog]);

        $identityTables = $this->moduleOwnedTableNames()['identity'] ?? [];
        self::assertNotEmpty($identityTables);
        self::assertTrue($this->containsTableReference(
            '<?php $query = DB::table(\''.$identityTables[0].'\');',
            $identityTables,
        ));

        self::assertFalse($this->globalModuleSurfaceAllowed(
            'App\\Modules\\Core\\Identity\\Infrastructure\\Persistence\\User',
        ));
        self::assertTrue($this->globalModuleSurfaceAllowed(
            'App\\Modules\\Core\\Identity\\Application\\Public\\Contracts\\UserLookup',
        ));
        self::assertTrue($this->globalModuleSurfaceAllowed(
            'App\\Modules\\Core\\Identity\\Presentation\\Http\\Middleware\\RequireAdministrativeMode',
        ));
    }

    public function test_declared_dependencies_are_used_by_real_or_shared_semantic_imports(): void
    {
        self::assertSame([], $this->declaredUnusedDependencyEdges());
    }

    /**
     * @return array<string, ModuleDefinition>
     */
    private function deployedModules(): array
    {
        $modules = [];

        foreach ($this->configuredModuleClasses() as $moduleClass) {
            $module = new $moduleClass;
            $modules[$module->key()->value] = $module;
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    private function undeclaredModuleImportEdges(): array
    {
        $moduleMap = $this->moduleNamespaceMap();
        $declared = [];
        $edges = [];

        foreach ($this->deployedModules() as $key => $module) {
            $declared[$key] = array_values(array_unique([
                ...array_map(static fn ($dependency): string => $dependency->value, $module->requiredDependencies()),
                ...array_map(static fn ($dependency): string => $dependency->value, $module->optionalDependencies()),
            ]));
        }

        foreach ($this->modulePhpFiles() as $file) {
            $owner = $this->moduleKeyForPath($file->getPathname(), $moduleMap);

            if ($owner === null) {
                continue;
            }

            foreach ($this->importedModuleKeys($file->getPathname(), $moduleMap) as $imported) {
                if ($imported === $owner || in_array($imported, $declared[$owner] ?? [], true)) {
                    continue;
                }

                $edges[] = $owner.' -> '.$imported;
            }
        }

        sort($edges);

        return array_values(array_unique($edges));
    }

    /**
     * @return list<string>
     */
    private function declaredUnusedDependencyEdges(): array
    {
        $actual = [];
        $unused = [];

        foreach ($this->deployedModules() as $key => $module) {
            $actual[$key] = [];
        }

        $moduleMap = $this->moduleNamespaceMap();

        foreach ($this->modulePhpFiles() as $file) {
            $owner = $this->moduleKeyForPath($file->getPathname(), $moduleMap);

            if ($owner === null) {
                continue;
            }

            foreach ($this->importedModuleKeys($file->getPathname(), $moduleMap) as $imported) {
                if ($imported !== $owner) {
                    $actual[$owner][] = $imported;
                }
            }

            foreach ($this->sharedSemanticModuleDependencies($file->getPathname()) as $dependency) {
                $actual[$owner][] = $dependency;
            }
        }

        foreach ($this->runtimeOnlyDependencyEvidence() as $owner => $dependencies) {
            $actual[$owner] = [...($actual[$owner] ?? []), ...$dependencies];
        }

        foreach ($this->deployedModules() as $key => $module) {
            $declared = array_values(array_unique([
                ...array_map(static fn ($dependency): string => $dependency->value, $module->requiredDependencies()),
                ...array_map(static fn ($dependency): string => $dependency->value, $module->optionalDependencies()),
            ]));
            $used = array_values(array_unique($actual[$key] ?? []));

            foreach (array_diff($declared, $used) as $dependency) {
                $unused[] = $key.' -> '.$dependency;
            }
        }

        sort($unused);

        return $unused;
    }

    /**
     * @param  list<string>  $directories
     * @return list<array{file: string, module: string, class: string}>
     */
    private function moduleClassImportsOutsideModules(array $directories): array
    {
        $moduleMap = $this->moduleNamespaceMap();
        $imports = [];

        foreach ($this->phpFilesIn($directories) as $file) {
            foreach ($this->classImports($file->getPathname()) as $class) {
                foreach ($moduleMap as $namespace => $moduleKey) {
                    if (! str_starts_with($class, $namespace.'\\') && $class !== $namespace) {
                        continue;
                    }

                    $imports[] = [
                        'file' => $this->relativePath($file->getPathname()),
                        'module' => $moduleKey,
                        'class' => $class,
                    ];
                    break;
                }
            }
        }

        usort($imports, static fn (array $left, array $right): int => [$left['file'], $left['class']] <=> [$right['file'], $right['class']]);

        return $imports;
    }

    /**
     * @return list<array{owner: string, provider: string, file: string}>
     */
    private function foreignPublicPersistenceImports(): array
    {
        $moduleMap = $this->moduleNamespaceMap();
        $persistenceClasses = $this->publicPersistenceModuleMap();
        $imports = [];

        foreach ($this->modulePhpFiles() as $file) {
            $owner = $this->moduleKeyForPath($file->getPathname(), $moduleMap);

            if ($owner === null) {
                continue;
            }

            foreach ($this->classImports($file->getPathname()) as $class) {
                $provider = $persistenceClasses[$class] ?? null;

                if ($provider === null || $provider === $owner) {
                    continue;
                }

                $imports[] = [
                    'owner' => $owner,
                    'provider' => $provider,
                    'file' => $this->relativePath($file->getPathname()),
                ];
            }
        }

        usort($imports, static function (array $left, array $right): int {
            return [$left['owner'], $left['provider'], $left['file']]
                <=> [$right['owner'], $right['provider'], $right['file']];
        });

        return $imports;
    }

    /**
     * @param  list<array{owner: string, provider: string, file: string}>  $imports
     * @return list<string>
     */
    private function foreignPublicPersistenceSummary(array $imports): array
    {
        $summary = [];

        foreach ($imports as $import) {
            $key = $import['owner'].' -> '.$import['provider'];
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        ksort($summary);

        return array_map(
            static fn (string $key, int $count): string => $key.': '.$count,
            array_keys($summary),
            array_values($summary),
        );
    }

    /**
     * @return list<string>
     */
    private function foreignSchemaQualifiedTableReferences(): array
    {
        $moduleMap = $this->moduleNamespaceMap();
        $ownedTables = $this->moduleOwnedTableNames();
        $references = [];

        self::assertNotEmpty($ownedTables, 'The foreign SQL guard must inspect real module-owned tables.');

        foreach ($this->modulePhpFiles() as $file) {
            $owner = $this->moduleKeyForPath($file->getPathname(), $moduleMap);

            if ($owner === null) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);

            foreach ($ownedTables as $provider => $tables) {
                if ($provider === $owner) {
                    continue;
                }

                foreach ($tables as $table) {
                    if ($this->containsTableReference($contents, [$table])) {
                        $references[] = sprintf(
                            '%s -> %s (%s)',
                            $owner,
                            $provider,
                            $this->relativePath($file->getPathname()),
                        );
                    }
                }
            }
        }

        sort($references);

        return array_values(array_unique($references));
    }

    /** @param list<string> $tables */
    private function containsTableReference(string $source, array $tables): bool
    {
        foreach ($tables as $table) {
            if (str_contains($source, $table)) {
                return true;
            }
        }

        return false;
    }

    private function globalModuleSurfaceAllowed(string $class): bool
    {
        return str_contains($class, '\\Application\\Public\\')
            || str_contains($class, '\\Presentation\\');
    }

    /**
     * @return array<string, string>
     */
    private function moduleNamespaceMap(): array
    {
        $map = [];

        foreach ($this->deployedModules() as $key => $module) {
            $reflection = new ReflectionClass($module);
            $namespace = $reflection->getNamespaceName();
            $parts = explode('\\', $namespace);

            $map[implode('\\', array_slice($parts, 0, 4))] = $key;
        }

        krsort($map);

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function publicPersistenceModuleMap(): array
    {
        $map = [];
        $moduleMap = $this->moduleNamespaceMap();

        foreach ($this->modulePhpFiles() as $file) {
            $path = $file->getPathname();

            if (! str_contains($path, '/Infrastructure/Persistence/TableNames/')) {
                continue;
            }

            $class = $this->classNameFromFile($path);
            $module = $this->moduleKeyForPath($path, $moduleMap);

            if ($class !== null && $module !== null) {
                $map[$class] = $module;
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    private function moduleOwnedTableNames(): array
    {
        $moduleMap = $this->moduleNamespaceMap();
        $tables = [];

        foreach ($this->modulePhpFiles() as $file) {
            $path = $file->getPathname();

            if (! str_contains($path, '/Infrastructure/Persistence/TableNames/')) {
                continue;
            }

            $class = $this->classNameFromFile($path);
            $module = $this->moduleKeyForPath($path, $moduleMap);

            if ($class === null || $module === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach ($reflection->getConstants() as $value) {
                if (is_string($value) && str_contains($value, '.')) {
                    $tables[$module][] = $value;
                }
            }
        }

        foreach ($tables as &$moduleTables) {
            $moduleTables = array_values(array_unique($moduleTables));
            sort($moduleTables);
        }
        unset($moduleTables);

        ksort($tables);

        return $tables;
    }

    /**
     * @param  array<string, string>  $moduleMap
     * @return list<string>
     */
    private function importedModuleKeys(string $path, array $moduleMap): array
    {
        $modules = [];

        foreach ($this->classImports($path) as $class) {
            foreach ($moduleMap as $namespace => $moduleKey) {
                if (str_starts_with($class, $namespace.'\\') || $class === $namespace) {
                    $modules[] = $moduleKey;
                    break;
                }
            }
        }

        sort($modules);

        return array_values(array_unique($modules));
    }

    /**
     * @return list<string>
     */
    private function sharedSemanticModuleDependencies(string $path): array
    {
        $dependencies = [];

        foreach ($this->classImports($path) as $class) {
            if (str_starts_with($class, 'App\\Shared\\Application\\Audit\\')) {
                $dependencies[] = 'audit';
            }

            if (str_starts_with($class, 'App\\Shared\\Application\\Exports\\')) {
                $dependencies[] = 'exports';
            }

            if (str_starts_with($class, 'App\\Shared\\Application\\Authorization\\')) {
                $dependencies[] = 'authorization';
            }

            if (str_starts_with($class, 'App\\Shared\\Application\\Teams\\')) {
                $dependencies[] = 'teams';
            }

            if (str_starts_with($class, 'App\\Shared\\Application\\ManagedProcesses\\')) {
                $dependencies[] = 'managed_processes';
            }
        }

        sort($dependencies);

        return array_values(array_unique($dependencies));
    }

    /**
     * Runtime-only dependencies must have an explicit behavior test because no PHP import can prove them.
     *
     * @return array<string, list<string>>
     */
    private function runtimeOnlyDependencyEvidence(): array
    {
        $test = file_get_contents(base_path('tests/Feature/Foundation/ModuleGateRuntimeTest.php'));
        self::assertIsString($test);
        self::assertStringContainsString("moduleKey: 'feature_flags'", $test);
        self::assertStringContainsString("moduleKey: 'time_tracking'", $test);

        return ['time_tracking' => ['feature_flags']];
    }

    /**
     * @return list<string>
     */
    private function classImports(string $path): array
    {
        return (new PhpModuleReferenceScanner)->referencesInFile($path);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function modulePhpFiles(): iterable
    {
        yield from $this->phpFilesIn([base_path('app/Modules')]);
    }

    /**
     * @param  list<string>  $directories
     * @return iterable<SplFileInfo>
     */
    private function phpFilesIn(array $directories): iterable
    {
        foreach ($directories as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $candidate) {
                if (! $candidate instanceof SplFileInfo || ! $candidate->isFile() || $candidate->getExtension() !== 'php') {
                    continue;
                }

                yield $candidate;
            }
        }
    }

    /**
     * @param  array<string, string>  $moduleMap
     */
    private function moduleKeyForPath(string $path, array $moduleMap): ?string
    {
        $class = $this->classNameFromFile($path);

        if ($class === null) {
            return null;
        }

        foreach ($moduleMap as $namespace => $moduleKey) {
            if (str_starts_with($class, $namespace.'\\') || $class === $namespace) {
                return $moduleKey;
            }
        }

        return null;
    }

    private function classNameFromFile(string $path): ?string
    {
        $contents = file_get_contents($path);

        self::assertIsString($contents);

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $namespace) !== 1) {
            return null;
        }

        if (preg_match('/^(?:(?:final|readonly|abstract)\s+)*(?:class|interface|enum|trait)\s+([A-Za-z0-9_]+)/m', $contents, $class) !== 1) {
            return null;
        }

        return $namespace[1].'\\'.$class[1];
    }

    /**
     * @return list<class-string<ModuleDefinition>>
     */
    private function configuredModuleClasses(): array
    {
        $configured = require base_path('config/modules.php');

        self::assertIsArray($configured);
        self::assertArrayHasKey('deployed', $configured);
        self::assertIsArray($configured['deployed']);

        $classes = [];

        foreach ($configured['deployed'] as $moduleClass) {
            self::assertIsString($moduleClass);
            self::assertTrue(
                is_subclass_of($moduleClass, ModuleDefinition::class),
                sprintf('Configured module [%s] must implement ModuleDefinition.', $moduleClass),
            );
            $classes[] = $moduleClass;
        }

        return $classes;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
