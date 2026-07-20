<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;

final class ConfiguredProcessDefinitionRegistry implements ProcessDefinitionRegistry
{
    /** @var array<string, ProcessDefinition>|null */
    private ?array $definitions = null;

    public function all(): array
    {
        return array_values($this->definitions());
    }

    public function get(string $key): ?ProcessDefinition
    {
        return $this->definitions()[$key] ?? null;
    }

    /**
     * @return array<string, ProcessDefinition>
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        foreach (app()->tagged('atlas.managed_process_definitions') as $definition) {
            if ($definition instanceof ProcessDefinition) {
                $definitions[$definition->key] = $definition;
            }
        }

        $this->definitions = $definitions;

        return $definitions;
    }
}
