<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

interface UserSessionLimitResolver
{
    /**
     * @return array{inactivity: int, maximum: int}
     */
    public function limitsForUserId(int $userId, ?string $teamPublicId = null): array;
}
