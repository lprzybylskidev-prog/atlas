<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports;

use App\Modules\Optional\Reports\Presentation\Providers\ReportsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class ReportsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('reports');
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
            new ModuleKey('files'),
            new ModuleKey('managed_processes'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return ReportsServiceProvider::class;
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
        return ['reports'];
    }

    public function frontendEntrypoints(): array
    {
        return [];
    }
}
