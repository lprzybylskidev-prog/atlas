<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

interface AuthorizationFixtureBuilder
{
    public function assignWorkspaceAccess(string $actorPublicId, string $userPublicId, string $teamPublicId): void;
}
