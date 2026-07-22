<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class FeatureFlagsPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_INDEX = 'admin.feature-flags.index';

    public const ADMIN_GLOBAL_UPDATE = 'admin.feature-flags.global.update';

    public const ADMIN_TEAM_UPDATE = 'admin.feature-flags.team.update';

    public const ADMIN_TEAM_CLEAR = 'admin.feature-flags.team.clear';

    public const EVALUATE = 'feature-flags.evaluate';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_INDEX, 'View feature flag definitions, effective values, and history.'),
            new ModulePermissionDefinition(self::ADMIN_GLOBAL_UPDATE, 'Update global feature flag values.'),
            new ModulePermissionDefinition(self::ADMIN_TEAM_UPDATE, 'Update active-team feature flag overrides.'),
            new ModulePermissionDefinition(self::ADMIN_TEAM_CLEAR, 'Clear active-team feature flag overrides.'),
            new ModulePermissionDefinition(self::EVALUATE, 'Evaluate enabled feature flags after module activation and authorization checks.'),
        ];
    }
}
