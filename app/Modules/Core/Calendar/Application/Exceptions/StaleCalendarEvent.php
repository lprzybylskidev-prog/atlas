<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Exceptions;

use RuntimeException;

final class StaleCalendarEvent extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Calendar event changed before this operation completed.');
    }
}
