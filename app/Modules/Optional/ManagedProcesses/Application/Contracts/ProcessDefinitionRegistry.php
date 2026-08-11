<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Contracts;

use App\Shared\Application\ManagedProcesses\DTOs\ProcessDefinition;

interface ProcessDefinitionRegistry
{
    /**
     * @return list<ProcessDefinition>
     */
    public function all(): array;

    public function get(string $key): ?ProcessDefinition;
}
