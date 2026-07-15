<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Contracts;

interface PasswordHistoryRepository
{
    public function containsRecentPassword(int $userId, string $plainPassword, int $limit): bool;

    public function record(int $userId, string $passwordHash, int $limit): void;
}
