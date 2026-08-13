<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Exceptions;

use RuntimeException;

final class CalendarEventNotFound extends RuntimeException
{
    public static function ownedEvent(): self
    {
        return new self('Calendar event was not found for the current owner.');
    }
}
