<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox\Contracts;

use App\Shared\Application\Outbox\IntegrationEventMessage;

interface OutboxEventRecorder
{
    public function record(IntegrationEventMessage $event): void;
}
