<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application\Indexing;

use App\Modules\Optional\Search\Presentation\Jobs\HandleSearchOutboxEvent;
use App\Shared\Application\Outbox\IntegrationEventMessage;

final class SearchOutboxEventDispatcher
{
    public function dispatch(IntegrationEventMessage $event): void
    {
        dispatch(HandleSearchOutboxEvent::fromMessage($event)->onQueue('search'))->afterCommit();
    }
}
