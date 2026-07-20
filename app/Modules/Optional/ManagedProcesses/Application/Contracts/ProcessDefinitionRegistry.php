<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Contracts;

use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;

interface ProcessDefinitionRegistry
{
    /**
     * @return list<ProcessDefinition>
     */
    public function all(): array;

    public function get(string $key): ?ProcessDefinition;
}
