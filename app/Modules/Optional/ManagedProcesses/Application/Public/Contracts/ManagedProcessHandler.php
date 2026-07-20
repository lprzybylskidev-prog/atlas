<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\Contracts;

interface ManagedProcessHandler
{
    public function processKey(): string;

    public function handle(string $runPublicId): void;
}
