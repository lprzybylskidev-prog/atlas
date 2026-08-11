<?php

declare(strict_types=1);

namespace App\Shared\Application\ManagedProcesses\Contracts;

interface ManagedProcessFixtureBuilder
{
    public function provideImportVisibilityRun(string $actorUserPublicId, string $teamPublicId): int;
}
