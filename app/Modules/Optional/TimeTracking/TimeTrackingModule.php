<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking;

use App\Modules\Optional\TimeTracking\Presentation\Providers\TimeTrackingServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class TimeTrackingModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('time_tracking');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Optional;
    }

    public function requiredDependencies(): array
    {
        return [
            new ModuleKey('identity'),
            new ModuleKey('authorization'),
            new ModuleKey('teams'),
            new ModuleKey('audit'),
            new ModuleKey('settings'),
            new ModuleKey('notifications'),
            new ModuleKey('health'),
            new ModuleKey('feature_flags'),
            new ModuleKey('managed_processes'),
            new ModuleKey('exports'),
            new ModuleKey('privacy'),
            new ModuleKey('reports'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return TimeTrackingServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function integrations(): array
    {
        return [];
    }

    public function healthChecks(): array
    {
        return ['time_tracking'];
    }

    public function frontendEntrypoints(): array
    {
        return [];
    }
}
