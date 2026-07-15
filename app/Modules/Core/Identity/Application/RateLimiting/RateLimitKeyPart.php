<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\RateLimiting;

enum RateLimitKeyPart: string
{
    case Ip = 'ip';
    case User = 'user';
    case Team = 'team';
    case ApiClient = 'api_client';
}
