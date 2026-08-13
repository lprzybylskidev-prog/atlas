<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Public\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FreeBusyWindow
{
    public function __construct(
        public string $userPublicId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if (trim($userPublicId) === '') {
            throw new InvalidArgumentException('Free/busy user public ID is required.');
        }

        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Free/busy window end must be after its start.');
        }
    }
}
