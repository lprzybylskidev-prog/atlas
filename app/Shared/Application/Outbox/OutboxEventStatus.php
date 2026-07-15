<?php

declare(strict_types=1);

namespace App\Shared\Application\Outbox;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
}
