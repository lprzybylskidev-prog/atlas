<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations;

use App\Modules\Optional\Integrations\Presentation\Providers\IntegrationsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class IntegrationsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('integrations');
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
            new ModuleKey('health'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return IntegrationsServiceProvider::class;
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
        return ['integrations'];
    }

    public function frontendEntrypoints(): array
    {
        return ['admin.integrations.index'];
    }
}
