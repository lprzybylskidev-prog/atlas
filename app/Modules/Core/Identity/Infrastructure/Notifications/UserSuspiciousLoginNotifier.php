<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Notifications;

use App\Modules\Core\Identity\Application\Contracts\SuspiciousLoginNotifier;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Carbon;

final class UserSuspiciousLoginNotifier implements SuspiciousLoginNotifier
{
    public function accountLocked(User $user, Carbon $lockedUntil): void
    {
        $user->notify(new AccountLockedNotification($lockedUntil));
    }
}
