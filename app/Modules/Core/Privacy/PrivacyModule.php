<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy;

use App\Modules\Core\Privacy\Presentation\Providers\PrivacyServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class PrivacyModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('privacy');
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
            new ModuleKey('files'),
            new ModuleKey('managed_processes'),
            new ModuleKey('exports'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [
            new ModuleKey('search'),
        ];
    }

    public function serviceProvider(): string
    {
        return PrivacyServiceProvider::class;
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
        return ['admin.privacy-retention.index'];
    }
}
