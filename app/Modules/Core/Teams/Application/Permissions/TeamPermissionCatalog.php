<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Permissions;

use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class TeamPermissionCatalog implements ModulePermissionContribution
{
    public const TEAMS_VIEW = TeamPermissionNames::TEAMS_VIEW;

    public const ADMIN_TEAMS_INDEX = TeamPermissionNames::ADMIN_TEAMS_INDEX;

    public const ADMIN_TEAMS_CREATE = 'admin.teams.create';

    public const ADMIN_TEAMS_STORE = 'admin.teams.store';

    public const ADMIN_TEAMS_EDIT = 'admin.teams.edit';

    public const ADMIN_TEAMS_UPDATE = 'admin.teams.update';

    public const ADMIN_TEAMS_ACTIVATE = 'admin.teams.activate';

    public const ADMIN_TEAMS_DEACTIVATE = 'admin.teams.deactivate';

    public const ADMIN_TEAMS_DELETE = 'admin.teams.destroy';

    public const TEAMS_CREATE = TeamPermissionNames::TEAMS_CREATE;

    public const TEAMS_UPDATE = TeamPermissionNames::TEAMS_UPDATE;

    public const TEAMS_DELETE = TeamPermissionNames::TEAMS_DELETE;

    public const MANAGERS_VIEW = TeamPermissionNames::MANAGERS_VIEW;

    public const MANAGERS_UPDATE = TeamPermissionNames::MANAGERS_UPDATE;

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::TEAMS_VIEW, 'View teams.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_INDEX, 'View team administration.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_CREATE, 'Open team creation.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STORE, 'Create teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_EDIT, 'Open team editing.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_UPDATE, 'Update teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_ACTIVATE, 'Activate teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_DEACTIVATE, 'Deactivate teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_DELETE, 'Delete teams through Admin UI.'),
            new ModulePermissionDefinition(self::TEAMS_CREATE, 'Create teams.'),
            new ModulePermissionDefinition(self::TEAMS_UPDATE, 'Update teams.'),
            new ModulePermissionDefinition(self::TEAMS_DELETE, 'Delete teams.'),
            new ModulePermissionDefinition(self::MANAGERS_VIEW, 'View manager hierarchy.'),
            new ModulePermissionDefinition(self::MANAGERS_UPDATE, 'Update manager hierarchy.'),
        ];
    }
}
