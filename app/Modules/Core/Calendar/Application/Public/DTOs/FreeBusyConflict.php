<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

final readonly class FreeBusyConflict
{
    public function __construct(
        public string $userPublicId,
        public int $busyWindowCount,
    ) {}
}
