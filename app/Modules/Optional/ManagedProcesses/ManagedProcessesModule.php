<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses;

use App\Modules\Optional\ManagedProcesses\Presentation\Providers\ManagedProcessesServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class ManagedProcessesModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('managed_processes');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Optional;
    }

    public function requiredDependencies(): array
    {
        return [
            new ModuleKey('identity'),
            new ModuleKey('teams'),
            new ModuleKey('audit'),
            new ModuleKey('files'),
            new ModuleKey('notifications'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return ManagedProcessesServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function healthChecks(): array
    {
        return [];
    }
}
