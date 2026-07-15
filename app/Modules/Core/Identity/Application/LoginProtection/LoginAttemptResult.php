<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\LoginProtection;

use Illuminate\Support\Carbon;

final readonly class LoginAttemptResult
{
    public function __construct(
        public int $failedAttempts,
        public bool $locked,
        public ?Carbon $lockedUntil,
    ) {}
}
