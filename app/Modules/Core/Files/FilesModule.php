<?php

declare(strict_types=1);

namespace App\Modules\Core\Files;

use App\Modules\Core\Files\Presentation\Providers\FilesServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class FilesModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('files');
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
            new ModuleKey('health'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return FilesServiceProvider::class;
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
        return ['clamav'];
    }

    public function frontendEntrypoints(): array
    {
        return ['admin.files.index'];
    }
}
