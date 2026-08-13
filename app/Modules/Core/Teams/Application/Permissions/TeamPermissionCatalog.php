<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;
use App\Shared\Application\Teams\Permissions\TeamPermissionNames;

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

    public const ADMIN_TEAMS_USERS_STORE = 'admin.teams.users.store';

    public const ADMIN_TEAMS_USERS_DESTROY = 'admin.teams.users.destroy';

    public const ADMIN_TEAMS_USERS_AUTHORIZATION_UPDATE = 'admin.teams.users.authorization.update';

    public const TEAMS_CREATE = TeamPermissionNames::TEAMS_CREATE;

    public const TEAMS_UPDATE = TeamPermissionNames::TEAMS_UPDATE;

    public const TEAMS_DELETE = TeamPermissionNames::TEAMS_DELETE;

    public const MANAGERS_VIEW = TeamPermissionNames::MANAGERS_VIEW;

    public const ADMIN_TEAMS_STRUCTURE_SHOW = 'admin.teams.structure.show';

    public const ADMIN_TEAMS_STRUCTURE_MEMBERS_STORE = 'admin.teams.structure.members.store';

    public const ADMIN_TEAMS_STRUCTURE_MEMBERS_DESTROY = 'admin.teams.structure.members.destroy';

    public const ADMIN_TEAMS_STRUCTURE_RELATIONSHIPS_STORE = 'admin.teams.structure.relationships.store';

    public const ADMIN_TEAMS_STRUCTURE_RELATIONSHIPS_END = 'admin.teams.structure.relationships.end';

    public const ADMIN_TEAMS_STRUCTURE_STRUCTURAL_ROLE_UPDATE = 'admin.teams.structure.structural-role.update';

    public const MANAGERS_CREATE = TeamPermissionNames::MANAGERS_CREATE;

    public const MANAGERS_UPDATE = TeamPermissionNames::MANAGERS_UPDATE;

    public const MANAGERS_TERMINATE = TeamPermissionNames::MANAGERS_TERMINATE;

    public const MANAGERS_TREE = TeamPermissionNames::MANAGERS_TREE;

    public const MANAGERS_HISTORY = TeamPermissionNames::MANAGERS_HISTORY;

    public const MANAGERS_HEAD_UPDATE = TeamPermissionNames::MANAGERS_HEAD_UPDATE;

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
            new ModulePermissionDefinition(self::ADMIN_TEAMS_USERS_STORE, 'Add users to teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_USERS_DESTROY, 'Remove users from teams through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_USERS_AUTHORIZATION_UPDATE, 'Update user authorization from team administration.'),
            new ModulePermissionDefinition(self::TEAMS_CREATE, 'Create teams.'),
            new ModulePermissionDefinition(self::TEAMS_UPDATE, 'Update teams.'),
            new ModulePermissionDefinition(self::TEAMS_DELETE, 'Delete teams.'),
            new ModulePermissionDefinition(self::MANAGERS_VIEW, 'View manager hierarchy.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_SHOW, 'View the integrated team structure editor.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_MEMBERS_STORE, 'Add team members through the integrated structure editor.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_MEMBERS_DESTROY, 'End team membership through the integrated structure editor.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_RELATIONSHIPS_STORE, 'Create manager relationships through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_RELATIONSHIPS_END, 'End manager relationships through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_TEAMS_STRUCTURE_STRUCTURAL_ROLE_UPDATE, 'Update structural roles through Admin UI.'),
            new ModulePermissionDefinition(self::MANAGERS_CREATE, 'Create manager relationships.'),
            new ModulePermissionDefinition(self::MANAGERS_UPDATE, 'Update manager hierarchy.'),
            new ModulePermissionDefinition(self::MANAGERS_TERMINATE, 'End manager relationships.'),
            new ModulePermissionDefinition(self::MANAGERS_TREE, 'View manager hierarchy tree.'),
            new ModulePermissionDefinition(self::MANAGERS_HISTORY, 'View manager relationship history.'),
            new ModulePermissionDefinition(self::MANAGERS_HEAD_UPDATE, 'Update head manager status.'),
        ];
    }
}
