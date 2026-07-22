<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags;

use App\Modules\Optional\FeatureFlags\Presentation\Providers\FeatureFlagsServiceProvider;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;

final class FeatureFlagsModule implements ModuleDefinition
{
    public function key(): ModuleKey
    {
        return new ModuleKey('feature_flags');
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
        return FeatureFlagsServiceProvider::class;
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
        return ['feature_flags'];
    }

    public function frontendEntrypoints(): array
    {
        return ['admin.feature-flags.index'];
    }
}
