<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Contracts;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Carbon;

interface SuspiciousLoginNotifier
{
    public function accountLocked(User $user, Carbon $lockedUntil): void;
}
