<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Contracts;

use App\Modules\Optional\FeatureFlags\Application\DTOs\FeatureFlagDefinition;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;

interface FeatureFlagRegistry
{
    /**
     * @return list<FeatureFlagDefinition>
     */
    public function all(): array;

    public function get(FeatureFlagKey|string $key): ?FeatureFlagDefinition;
}
