<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox\Contracts;

use App\Shared\Application\Outbox\OutboxRelayResult;

interface OutboxRelay
{
    public function publishPending(int $limit = 100): OutboxRelayResult;
}
