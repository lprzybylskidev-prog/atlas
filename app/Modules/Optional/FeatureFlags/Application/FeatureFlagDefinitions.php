<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application;

use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\DTOs\FeatureFlagDefinition;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagValueType;

final class FeatureFlagDefinitions implements FeatureFlagRegistry
{
    public function all(): array
    {
        return [
            new FeatureFlagDefinition(
                key: FeatureFlagKey::ReportsPreview,
                name: 'Reports preview',
                description: 'Controls preview access to report, export, PDF, chart, and print rollout surfaces.',
                type: FeatureFlagValueType::Boolean,
                defaultEnabled: false,
                teamScoped: true,
                ownerModule: 'reports',
                lifecycle: 'planned',
            ),
            new FeatureFlagDefinition(
                key: FeatureFlagKey::PrivacyWorkflowPreview,
                name: 'Privacy workflow preview',
                description: 'Controls preview access to retention, deletion, anonymization, and legal-hold workflows.',
                type: FeatureFlagValueType::Boolean,
                defaultEnabled: false,
                teamScoped: true,
                ownerModule: 'privacy',
                lifecycle: 'planned',
            ),
            new FeatureFlagDefinition(
                key: FeatureFlagKey::TimeTrackingPreview,
                name: 'TimeTracking preview',
                description: 'Controls preview access to optional TimeTracking rollout behavior after module activation and authorization pass.',
                type: FeatureFlagValueType::Boolean,
                defaultEnabled: false,
                teamScoped: true,
                ownerModule: 'time_tracking',
                lifecycle: 'planned',
            ),
        ];
    }

    public function get(FeatureFlagKey|string $key): ?FeatureFlagDefinition
    {
        $value = $key instanceof FeatureFlagKey ? $key->value : $key;

        foreach ($this->all() as $definition) {
            if ($definition->key->value === $value) {
                return $definition;
            }
        }

        return null;
    }
}
