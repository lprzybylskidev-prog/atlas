<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Contracts;

interface AdministratorAccessManager
{
    public function administratorExists(): bool;

    public function assignAdministrator(string $userPublicId, string $teamPublicId): void;
}
