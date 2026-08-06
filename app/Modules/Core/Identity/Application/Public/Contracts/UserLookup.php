<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

interface UserLookup
{
    public function internalIdForPublicId(string $userPublicId): ?int;

    public function publicIdForInternalId(int $userId): ?string;
}
