<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class IntegrationsPermissionCatalog implements ModulePermissionContribution
{
    public const ADMIN_INTEGRATIONS_INDEX = 'admin.integrations.index';

    public const ADMIN_INTEGRATIONS_TEST = 'admin.integrations.test';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::ADMIN_INTEGRATIONS_INDEX, 'View integration status, synchronization history, circuit states, and external API boundary status.'),
            new ModulePermissionDefinition(self::ADMIN_INTEGRATIONS_TEST, 'Run permission-protected integration test-connection actions.'),
        ];
    }
}
