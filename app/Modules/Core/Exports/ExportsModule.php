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
            new ModuleKey('teams'),
            new ModuleKey('notifications'),
            new ModuleKey('files'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [
            new ModuleKey('managed_processes'),
        ];
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

    public function healthChecks(): array
    {
        return [];
    }
}
