<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Contracts;

interface TeamStructureMutationGuard
{
    public function assertReparentAllowed(
        string $teamPublicId,
        string $reportUserPublicId,
        string $currentManagerUserPublicId,
        string $newManagerUserPublicId,
    ): void;
}
