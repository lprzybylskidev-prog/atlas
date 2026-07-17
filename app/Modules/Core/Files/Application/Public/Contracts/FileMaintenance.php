<?php

declare(strict_types=1);

namespace App\Modules\Core\Files\Application\Public\Contracts;

use App\Modules\Core\Files\Application\Public\DTOs\FileMaintenanceResult;

interface FileMaintenance
{
    public function pruneTemporaryFiles(int $ttlMinutes): FileMaintenanceResult;
}
