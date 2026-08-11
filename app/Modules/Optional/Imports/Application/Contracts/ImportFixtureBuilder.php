<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Application\Contracts;

interface ImportFixtureBuilder
{
    public function provideVisibilityImport(string $actorUserPublicId, string $teamPublicId): void;
}
