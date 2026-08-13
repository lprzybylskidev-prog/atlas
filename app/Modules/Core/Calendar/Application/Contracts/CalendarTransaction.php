<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

use Closure;

interface CalendarTransaction
{
    /** @param Closure(): void $operation */
    public function run(Closure $operation): void;
}
