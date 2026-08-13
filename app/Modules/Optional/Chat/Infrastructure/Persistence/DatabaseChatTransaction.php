<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Persistence;

use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseChatTransaction implements ChatTransaction
{
    public function __construct(private ConnectionInterface $database) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        return $this->database->transaction($callback, 3);
    }
}
