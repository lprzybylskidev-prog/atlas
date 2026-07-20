<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Enums;

enum IntegrationCircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
