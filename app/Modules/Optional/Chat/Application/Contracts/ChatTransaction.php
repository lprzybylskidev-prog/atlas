<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application\Contracts;

use Closure;

interface ChatTransaction
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}
