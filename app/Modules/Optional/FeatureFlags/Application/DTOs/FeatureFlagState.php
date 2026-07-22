<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\DTOs;

final readonly class FeatureFlagState
{
    /**
     * @param  array<string, mixed>|null  $globalValue
     * @param  array<string, mixed>|null  $teamValue
     * @param  array<string, mixed>  $effectiveValue
     */
    public function __construct(
        public FeatureFlagDefinition $definition,
        public array $effectiveValue,
        public string $source,
        public ?array $globalValue = null,
        public ?array $teamValue = null,
        public ?string $teamPublicId = null,
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->effectiveValue['enabled'] ?? false);
    }
}
