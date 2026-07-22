<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports;

use App\Modules\Core\Exports\Presentation\Providers\ExportsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class ExportsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('exports');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Core;
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
        return ExportsServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return false;
    }

    public function supportsTeamActivation(): bool
    {
        return false;
    }

    public function integrations(): array
    {
        return [];
    }

    public function healthChecks(): array
    {
        return ['exports'];
    }

    public function frontendEntrypoints(): array
    {
        return [];
    }
}
