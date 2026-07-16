<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

enum StarterRoleName: string
{
    case WorkspaceAccess = 'workspace.access';
    case AdminUsersRead = 'admin.users.read';
    case AdminUsersManage = 'admin.users.manage';
    case AdminTeamsRead = 'admin.teams.read';
    case AdminTeamsManage = 'admin.teams.manage';
    case AuthorizationRolesRead = 'authorization.roles.read';
    case AuthorizationRolesManage = 'authorization.roles.manage';
    case AuthorizationPresetsManage = 'authorization.presets.manage';
    case AuthorizationPermissionsRead = 'authorization.permissions.read';
    case TeamManagersRead = 'teams.managers.read';
    case TeamManagersManage = 'teams.managers.manage';
    case SystemStatusRead = 'system.status.read';
    case SystemOperationsManage = 'system.operations.manage';
    case Administrator = 'system.administrator';
}
