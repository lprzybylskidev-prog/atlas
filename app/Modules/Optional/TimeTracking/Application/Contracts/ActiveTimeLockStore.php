<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Contracts;

use App\Modules\Optional\TimeTracking\Application\DTOs\ActiveTimeLock;

interface ActiveTimeLockStore
{
    public function activeForUser(int $userId): ?ActiveTimeLock;
}
