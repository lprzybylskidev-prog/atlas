<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Public\DTOs;

final readonly class FeatureFlagEvaluation
{
    public function __construct(
        public string $key,
        public bool $enabled,
        public string $source,
        public ?string $teamPublicId,
    ) {}
}
