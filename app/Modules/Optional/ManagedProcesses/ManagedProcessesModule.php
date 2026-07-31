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
            new ModuleKey('authorization'),
            new ModuleKey('teams'),
            new ModuleKey('audit'),
            new ModuleKey('notifications'),
            new ModuleKey('health'),
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

    public function integrations(): array
    {
        return [];
    }

    public function healthChecks(): array
    {
        return ['managed_processes'];
    }

    public function frontendEntrypoints(): array
    {
        return [
            'admin.managed-processes.index',
            'admin.managed-processes.definitions.index',
            'admin.managed-processes.schedules.index',
            'admin.managed-processes.schedules.create',
        ];
    }
}
