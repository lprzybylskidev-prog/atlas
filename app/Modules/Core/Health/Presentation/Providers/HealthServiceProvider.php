<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Presentation\Providers;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Health\Infrastructure\Readiness\AtlasReadinessChecker;
use Illuminate\Support\ServiceProvider;

final class HealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReadinessChecker::class, AtlasReadinessChecker::class);
    }
}
