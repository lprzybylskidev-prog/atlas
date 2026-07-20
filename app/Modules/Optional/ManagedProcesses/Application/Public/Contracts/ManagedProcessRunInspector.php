<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\Contracts;

interface ManagedProcessRunInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inputSnapshot(string $runPublicId): array;
}
