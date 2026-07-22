<?php

declare(strict_types=1);

namespace Tests\Unit\FeatureFlags;

use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Modules\Optional\FeatureFlags\Application\FeatureFlagDefinitions;
use App\Modules\Optional\FeatureFlags\FeatureFlagsModule;
use App\Shared\Application\Modules\ModuleCategory;
use Tests\TestCase;

final class FeatureFlagDefinitionsTest extends TestCase
{
    public function test_feature_flags_module_is_optional_and_separate_from_module_activation(): void
    {
        $module = new FeatureFlagsModule;

        self::assertSame('feature_flags', $module->key()->value);
        self::assertSame(ModuleCategory::Optional, $module->category());
        self::assertTrue($module->supportsGlobalActivation());
        self::assertTrue($module->supportsTeamActivation());
        self::assertSame(['admin.feature-flags.index'], $module->frontendEntrypoints());
    }

    public function test_registered_flags_are_typed_and_default_disabled(): void
    {
        $registry = new FeatureFlagDefinitions;
        $definition = $registry->get(FeatureFlagKey::ReportsPreview);

        self::assertNotNull($definition);
        self::assertSame('reports.preview', $definition->key->value);
        self::assertSame('boolean', $definition->type->value);
        self::assertFalse($definition->defaultEnabled);
        self::assertTrue($definition->teamScoped);
    }
}
