<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Domain\Exceptions;

use DateTimeImmutable;
use InvalidArgumentException;

final class InvalidTimeInterval extends InvalidArgumentException
{
    public static function becauseStartMustPrecedeEnd(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): self
    {
        return new self(sprintf(
            'TimeTracking interval start [%s] must precede end [%s].',
            $startsAt->format(DATE_ATOM),
            $endsAt->format(DATE_ATOM),
        ));
    }
}
