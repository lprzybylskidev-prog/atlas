<?php

declare(strict_types=1);

namespace App\Modules\Core\Calendar\Application\Permissions;

use App\Shared\Application\Calendar\Permissions\CalendarPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class CalendarPermissionCatalog implements ModulePermissionContribution
{
    public const INDEX = CalendarPermissionNames::INDEX;

    public const EVENT_STORE = CalendarPermissionNames::EVENT_STORE;

    public const EVENT_UPDATE = CalendarPermissionNames::EVENT_UPDATE;

    public const EVENT_DESTROY = CalendarPermissionNames::EVENT_DESTROY;

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::INDEX, 'Use the shared Atlas Calendar.'),
            new ModulePermissionDefinition(self::EVENT_STORE, 'Create personal Calendar events.'),
            new ModulePermissionDefinition(self::EVENT_UPDATE, 'Update own personal Calendar events.'),
            new ModulePermissionDefinition(self::EVENT_DESTROY, 'Delete own personal Calendar events.'),
        ];
    }
}
