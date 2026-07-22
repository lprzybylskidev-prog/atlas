<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application;

use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\Public\Contracts\FeatureFlagEvaluator;
use App\Modules\Optional\FeatureFlags\Application\Public\DTOs\FeatureFlagEvaluation;

final readonly class FeatureFlagService implements FeatureFlagEvaluator
{
    public function __construct(private FeatureFlagStore $store) {}

    public function evaluate(FeatureFlagKey|string $key, ?string $teamPublicId = null): FeatureFlagEvaluation
    {
        $state = $this->store->state($key, $teamPublicId);

        return new FeatureFlagEvaluation(
            key: $state->definition->key->value,
            enabled: $state->enabled(),
            source: $state->source,
            teamPublicId: $state->teamPublicId,
        );
    }

    public function enabled(FeatureFlagKey|string $key, ?string $teamPublicId = null): bool
    {
        return $this->evaluate($key, $teamPublicId)->enabled;
    }
}
