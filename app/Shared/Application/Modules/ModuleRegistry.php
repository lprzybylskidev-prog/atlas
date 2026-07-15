<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Exceptions\DuplicateModuleKey;
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

        unset($visiting[$key]);
        $visited[$key] = true;
        $ordered[] = $module;
    }
}
