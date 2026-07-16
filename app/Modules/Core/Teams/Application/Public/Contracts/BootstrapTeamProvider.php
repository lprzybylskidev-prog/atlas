<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

use App\Modules\Core\Teams\Application\Public\DTOs\BootstrapTeam;

interface BootstrapTeamProvider
{
    public function provide(string $name): BootstrapTeam;
}
