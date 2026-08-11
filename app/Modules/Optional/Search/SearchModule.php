<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search;

use App\Modules\Optional\Search\Presentation\Providers\SearchServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class SearchModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('search');
    }

    public function category(): ModuleCategory
    {
        return ModuleCategory::Optional;
    }

    public function requiredDependencies(): array
    {
        return [
            new ModuleKey('managed_processes'),
        ];
    }

    public function optionalDependencies(): array
    {
        return [];
    }

    public function serviceProvider(): string
    {
        return SearchServiceProvider::class;
    }

    public function supportsGlobalActivation(): bool
    {
        return true;
    }

    public function supportsTeamActivation(): bool
    {
        return true;
    }

    public function healthChecks(): array
    {
        return ['meilisearch'];
    }
}
