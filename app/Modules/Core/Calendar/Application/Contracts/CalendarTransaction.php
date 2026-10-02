<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Contracts;

use Closure;

interface CalendarTransaction
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function run(Closure $operation): mixed;
}
