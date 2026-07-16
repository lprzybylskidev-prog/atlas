<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings;

use App\Modules\Core\Settings\Presentation\Providers\SettingsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class SettingsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('settings');
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
            new ModuleKey('audit'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return SettingsServiceProvider::class;
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
        return [];
    }
}
