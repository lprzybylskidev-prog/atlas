<?php

declare(strict_types=1);

namespace App\Modules\Core\Health;

use App\Modules\Core\Health\Presentation\Providers\HealthServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class HealthModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('health');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Core;
    }

    public function requiredDependencies(): array
    {
        return [];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return HealthServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return false;
    }

    public function supportsTeamActivation(): bool
    {
        return false;
    }

    public function healthChecks(): array
    {
        return [];
    }
}
