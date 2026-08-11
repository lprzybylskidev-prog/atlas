<?php

declare(strict_types=1);

namespace App\Shared\Application\ManagedProcesses\Contracts;

interface ManagedProcessHandler
{
    public function processKey(): string;

    public function handle(string $runPublicId): void;
}
