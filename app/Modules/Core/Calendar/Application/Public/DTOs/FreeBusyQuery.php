<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FreeBusyQuery
{
    /** @param list<string> $userPublicIds */
    public function __construct(
        public array $userPublicIds,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($userPublicIds === [] || count($userPublicIds) !== count(array_unique($userPublicIds))) {
            throw new InvalidArgumentException('Free/busy users must be non-empty and unique.');
        }

        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Free/busy query end must be after its start.');
        }
    }
}
