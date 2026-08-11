<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Exceptions\DuplicateModuleKey;
use App\Shared\Application\Modules\Exceptions\InvalidModuleDefinition;
use App\Shared\Application\Modules\Exceptions\MissingRequiredModuleDependency;
use App\Shared\Application\Modules\Exceptions\ModuleDependencyCycle;

final class ModuleRegistry
{
    /** @var array<string, ModuleDefinition> */
    private array $modules = [];

    /** @var list<ModuleDefinition> */
    private array $startupOrder;

    /**
     * @param  iterable<ModuleDefinition>  $modules
     */
    public function __construct(iterable $modules)
    {
        foreach ($modules as $module) {
            $key = $module->key()->value;

            if (array_key_exists($key, $this->modules)) {
                throw DuplicateModuleKey::forKey($module->key());
            }

            $this->modules[$key] = $module;
        }

        $this->validateModuleMetadata();
        $this->validateRequiredDependencies();
        $this->startupOrder = $this->computeStartupOrder();
    }

    public function has(ModuleKey $key): bool
    {
        return array_key_exists($key->value, $this->modules);
    }

    public function get(ModuleKey $key): ModuleDefinition
    {
        return $this->modules[$key->value]
            ?? throw MissingRequiredModuleDependency::forMissingModule($key);
    }

    /**
     * @return list<ModuleDefinition>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    /**
     * @return list<ModuleDefinition>
     */
    public function startupOrder(): array
    {
        return $this->startupOrder;
    }

    /**
     * @return list<ModuleDefinition>
     */
    public function requiredDependentsOf(ModuleKey $dependency): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (ModuleDefinition $module): bool => in_array(
                $dependency->value,
                array_map(static fn (ModuleKey $key): string => $key->value, $module->requiredDependencies()),
                true,
            ),
        ));
    }

    private function validateRequiredDependencies(): void
    {
        foreach ($this->modules as $module) {
            foreach ($module->requiredDependencies() as $dependency) {
                if (! $this->has($dependency)) {
                    throw MissingRequiredModuleDependency::forDependency($module->key(), $dependency);
                }
            }
        }
    }

    private function validateModuleMetadata(): void
    {
        foreach ($this->modules as $module) {
            $this->assertNoDuplicateDependencies($module);
            $this->assertCoreModuleDoesNotRequireOptionalModule($module);
            $this->assertServiceProviderExists($module);
            $this->assertUniqueNonEmptyMetadata($module, 'health checks', $module->healthChecks());
        }
    }

    private function assertNoDuplicateDependencies(ModuleDefinition $module): void
    {
        $required = array_map(static fn (ModuleKey $dependency): string => $dependency->value, $module->requiredDependencies());
        $optional = array_map(static fn (ModuleKey $dependency): string => $dependency->value, $module->optionalDependencies());

        foreach ([[$required, 'required'], [$optional, 'optional']] as [$dependencies, $type]) {
            $duplicates = $this->duplicates($dependencies);

            if ($duplicates !== []) {
                throw InvalidModuleDefinition::forReason($module->key(), sprintf(
                    'duplicate %s dependencies [%s].',
                    $type,
                    implode(', ', $duplicates),
                ));
            }
        }

        $overlap = array_values(array_intersect($required, $optional));

        if ($overlap !== []) {
            sort($overlap);

            throw InvalidModuleDefinition::forReason($module->key(), sprintf(
                'dependencies cannot be both required and optional [%s].',
                implode(', ', $overlap),
            ));
        }

        if (in_array($module->key()->value, [...$required, ...$optional], true)) {
            throw InvalidModuleDefinition::forReason($module->key(), 'a module cannot depend on itself.');
        }
    }

    private function assertCoreModuleDoesNotRequireOptionalModule(ModuleDefinition $module): void
    {
        if ($module->category() !== ModuleCategory::Core) {
            return;
        }

        foreach ($module->requiredDependencies() as $dependency) {
            $dependencyModule = $this->modules[$dependency->value] ?? null;

            if ($dependencyModule?->category() === ModuleCategory::Optional) {
                throw InvalidModuleDefinition::forReason($module->key(), sprintf(
                    'Core modules cannot require Optional module [%s]. Use an optional dependency with a reduced mode or extract a Core/Shared capability.',
                    $dependency->value,
                ));
            }
        }
    }

    private function assertServiceProviderExists(ModuleDefinition $module): void
    {
        if (! class_exists($module->serviceProvider())) {
            throw InvalidModuleDefinition::forReason($module->key(), sprintf(
                'service provider class [%s] does not exist.',
                $module->serviceProvider(),
            ));
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function assertUniqueNonEmptyMetadata(ModuleDefinition $module, string $label, array $values): void
    {
        $empty = array_filter($values, static fn (string $value): bool => trim($value) === '');

        if ($empty !== []) {
            throw InvalidModuleDefinition::forReason($module->key(), sprintf('%s cannot contain empty values.', $label));
        }

        $duplicates = $this->duplicates($values);

        if ($duplicates !== []) {
            throw InvalidModuleDefinition::forReason($module->key(), sprintf(
                '%s contain duplicate values [%s].',
                $label,
                implode(', ', $duplicates),
            ));
        }
    }

    /**
     * @return list<ModuleDefinition>
     */
    private function computeStartupOrder(): array
    {
        $ordered = [];
        $visiting = [];
        $visited = [];

        foreach (array_keys($this->modules) as $key) {
            $this->visit($key, $visiting, $visited, $ordered);
        }

        return $ordered;
    }

    /**
     * @param  array<string, true>  $visiting
     * @param  array<string, true>  $visited
     * @param  list<ModuleDefinition>  $ordered
     */
    private function visit(string $key, array &$visiting, array &$visited, array &$ordered): void
    {
        if (isset($visited[$key])) {
            return;
        }

        if (isset($visiting[$key])) {
            throw ModuleDependencyCycle::including(new ModuleKey($key));
        }

        $visiting[$key] = true;
        $module = $this->modules[$key];

        foreach ($module->requiredDependencies() as $dependency) {
            $this->visit($dependency->value, $visiting, $visited, $ordered);
        }

        foreach ($module->optionalDependencies() as $dependency) {
            if ($this->has($dependency)) {
                $this->visit($dependency->value, $visiting, $visited, $ordered);
            }
        }

        unset($visiting[$key]);
        $visited[$key] = true;
        $ordered[] = $module;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[] = $value;
            }

            $seen[$value] = true;
        }

        sort($duplicates);

        return array_values(array_unique($duplicates));
    }
}
