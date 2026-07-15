<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity;

use App\Modules\Core\Identity\Presentation\Providers\FortifyServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class IdentityModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('identity');
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
        return FortifyServiceProvider::class;
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
        return [];
    }

    public function frontendEntrypoints(): array
    {
        return ['Auth/Login'];
    }
}
