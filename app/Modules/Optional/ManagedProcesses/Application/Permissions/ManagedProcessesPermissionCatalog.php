<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class ManagedProcessesPermissionCatalog implements ModulePermissionContribution
{
    public const INDEX = 'admin.managed-processes.index';

    public const DEFINITIONS_INDEX = 'admin.managed-processes.definitions.index';

    public const SHOW = 'admin.managed-processes.show';

    public const RUN = 'admin.managed-processes.run';

    public const DEFINITIONS_RUN = 'admin.managed-processes.definitions.run';

    public const RETRY = 'admin.managed-processes.retry';

    public const CANCEL = 'admin.managed-processes.cancel';

    public const ACKNOWLEDGE = 'admin.managed-processes.acknowledge';

    public const SCHEDULES_INDEX = 'admin.managed-processes.schedules.index';

    public const SCHEDULES_CREATE = 'admin.managed-processes.schedules.create';

    public const SCHEDULES_STORE = 'admin.managed-processes.schedules.store';

    public const SCHEDULES_DISABLE = 'admin.managed-processes.schedules.disable';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::INDEX, 'View registered managed process definitions and run history.'),
            new ModulePermissionDefinition(self::DEFINITIONS_INDEX, 'View registered managed process definitions.'),
            new ModulePermissionDefinition(self::SHOW, 'View managed process run details, progress, counters, logs, input, and summaries.'),
            new ModulePermissionDefinition(self::RUN, 'Start registered manual managed processes.'),
            new ModulePermissionDefinition(self::DEFINITIONS_RUN, 'Start a registered managed process definition from the Admin process browser.'),
            new ModulePermissionDefinition(self::RETRY, 'Retry retryable failed or warning managed process runs.'),
            new ModulePermissionDefinition(self::CANCEL, 'Cancel cancellable managed process runs at safe checkpoints.'),
            new ModulePermissionDefinition(self::ACKNOWLEDGE, 'Mark failed, cancelled, expired, or warning managed process runs as handled.'),
            new ModulePermissionDefinition(self::SCHEDULES_INDEX, 'View managed process schedules.'),
            new ModulePermissionDefinition(self::SCHEDULES_CREATE, 'Open the Admin managed process schedule form.'),
            new ModulePermissionDefinition(self::SCHEDULES_STORE, 'Create managed process schedules for registered schedulable processes.'),
            new ModulePermissionDefinition(self::SCHEDULES_DISABLE, 'Disable managed process schedules.'),
        ];
    }
}
