<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox\Contracts;

use App\Shared\Application\Outbox\OutboxStoredEvent;

interface OutboxEventPublisher
{
    public function publish(OutboxStoredEvent $event): void;
}
