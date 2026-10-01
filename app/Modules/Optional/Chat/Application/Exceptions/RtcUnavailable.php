<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Exceptions;

use RuntimeException;
use Throwable;

final class RtcUnavailable extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('RTC media infrastructure is not enabled for this runtime.');
    }

    public static function infrastructure(Throwable $previous): self
    {
        return new self('RTC media infrastructure is currently unavailable.', previous: $previous);
    }
}
