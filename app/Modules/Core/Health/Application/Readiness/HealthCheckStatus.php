<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Application\Readiness;

enum HealthCheckStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
}
