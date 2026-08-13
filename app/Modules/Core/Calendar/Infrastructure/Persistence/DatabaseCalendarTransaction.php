<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Infrastructure\Persistence;

use App\Modules\Core\Calendar\Application\Contracts\CalendarTransaction;
use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseCalendarTransaction implements CalendarTransaction
{
    public function __construct(private ConnectionInterface $database) {}

    public function run(Closure $operation): void
    {
        $this->database->transaction($operation);
    }
}
