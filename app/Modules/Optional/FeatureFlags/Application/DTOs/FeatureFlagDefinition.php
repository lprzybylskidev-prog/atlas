<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\DTOs;

use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagValueType;

final readonly class FeatureFlagDefinition
{
    public function __construct(
        public FeatureFlagKey $key,
        public string $name,
        public string $description,
        public FeatureFlagValueType $type,
        public bool $defaultEnabled,
        public bool $teamScoped,
        public string $ownerModule,
        public string $lifecycle,
    ) {}
}
