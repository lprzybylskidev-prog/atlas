<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Services;

use App\Modules\Core\Teams\Application\Public\Contracts\TeamStructureMutationGuard;

final class AllowTeamStructureMutations implements TeamStructureMutationGuard
{
    public function assertReparentAllowed(
        string $teamPublicId,
        string $reportUserPublicId,
        string $currentManagerUserPublicId,
        string $newManagerUserPublicId,
    ): void {}
}
