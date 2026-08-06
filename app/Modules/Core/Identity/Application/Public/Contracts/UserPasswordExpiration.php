<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

use Carbon\CarbonImmutable;

interface UserPasswordExpiration
{
    public function expiresAtForUserId(int $userId): ?CarbonImmutable;

    public function expiresAfterDays(): int;
}
