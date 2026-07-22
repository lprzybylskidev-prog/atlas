<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Public\Contracts;

use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\Public\DTOs\FeatureFlagEvaluation;

interface FeatureFlagEvaluator
{
    public function evaluate(FeatureFlagKey|string $key, ?string $teamPublicId = null): FeatureFlagEvaluation;

    public function enabled(FeatureFlagKey|string $key, ?string $teamPublicId = null): bool;
}
