<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports;

use App\Modules\Optional\Imports\Presentation\Providers\ImportsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class ImportsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('imports');
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
            new ModuleKey('files'),
            new ModuleKey('integrations'),
            new ModuleKey('managed_processes'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return ImportsServiceProvider::class;
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
        return ['imports'];
    }

    public function frontendEntrypoints(): array
    {
        return ['admin.imports.index'];
    }
}
