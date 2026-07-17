<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Notifications\Application\Public\Permissions\NotificationPermissionNames;
use App\Modules\Core\Teams\Application\Public\Permissions\TeamPermissionNames;

final class StarterRoleCatalog
{
    /**
     * @param  list<string>  $allPermissionNames
     * @return list<StarterRoleDefinition>
     */
    public function definitions(array $allPermissionNames): array
    {
        return [
            new StarterRoleDefinition(StarterRoleName::WorkspaceAccess, [
                CoreAuthorizationPermissionCatalog::DASHBOARD,
                'team.switch',
                NotificationPermissionNames::NOTIFICATIONS_INDEX,
                NotificationPermissionNames::NOTIFICATIONS_READ,
                NotificationPermissionNames::NOTIFICATIONS_READ_BULK,
                NotificationPermissionNames::REALTIME_EVENTS,
            ]),
            new StarterRoleDefinition(StarterRoleName::AdminUsersRead, [
                'admin.users.index',
            ]),
            new StarterRoleDefinition(StarterRoleName::AdminUsersManage, [
                'admin.users.index',
                'admin.users.create',
                'admin.users.store',
                'admin.users.edit',
                'admin.users.update',
                'admin.users.activate',
                'admin.users.deactivate',
                'admin.users.verify-email',
                'admin.users.require-email-verification',
                'admin.users.resend-first-password',
                'admin.users.unlock',
                'admin.users.reset-mfa',
                'admin.users.invalidate-sessions',
                'admin.users.teams.store',
                'admin.users.teams.destroy',
                'admin.users.teams.authorization.update',
            ]),
            new StarterRoleDefinition(StarterRoleName::AdminTeamsRead, [
                'admin.teams.index',
            ]),
            new StarterRoleDefinition(StarterRoleName::AdminTeamsManage, [
                'admin.teams.index',
                'admin.teams.create',
                'admin.teams.store',
                'admin.teams.edit',
                'admin.teams.update',
                'admin.teams.activate',
                'admin.teams.deactivate',
                'admin.teams.destroy',
            ]),
            new StarterRoleDefinition(StarterRoleName::AuthorizationRolesRead, [
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES,
                CoreAuthorizationPermissionCatalog::ROLES_VIEW,
            ]),
            new StarterRoleDefinition(StarterRoleName::AuthorizationRolesManage, [
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES_CREATE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES_STORE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES_EDIT,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES_UPDATE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_ROLES_DELETE,
                CoreAuthorizationPermissionCatalog::ROLES_VIEW,
                CoreAuthorizationPermissionCatalog::ROLES_CREATE,
                CoreAuthorizationPermissionCatalog::ROLES_UPDATE,
                CoreAuthorizationPermissionCatalog::ROLES_DELETE,
            ]),
            new StarterRoleDefinition(StarterRoleName::AuthorizationPresetsManage, [
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES_CREATE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES_STORE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES_EDIT,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES_UPDATE,
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PACKAGES_DELETE,
            ]),
            new StarterRoleDefinition(StarterRoleName::AuthorizationPermissionsRead, [
                CoreAuthorizationPermissionCatalog::ADMIN_AUTHORIZATION_PERMISSIONS,
                CoreAuthorizationPermissionCatalog::PERMISSIONS_VIEW,
            ]),
            new StarterRoleDefinition(StarterRoleName::TeamManagersRead, [
                'admin.managers.index',
                TeamPermissionNames::MANAGERS_VIEW,
                TeamPermissionNames::MANAGERS_TREE,
                TeamPermissionNames::MANAGERS_HISTORY,
            ]),
            new StarterRoleDefinition(StarterRoleName::TeamManagersManage, [
                'admin.managers.index',
                'admin.managers.store',
                'admin.managers.end',
                'admin.managers.head.update',
                TeamPermissionNames::MANAGERS_VIEW,
                TeamPermissionNames::MANAGERS_CREATE,
                TeamPermissionNames::MANAGERS_UPDATE,
                TeamPermissionNames::MANAGERS_TERMINATE,
                TeamPermissionNames::MANAGERS_TREE,
                TeamPermissionNames::MANAGERS_HISTORY,
                TeamPermissionNames::MANAGERS_HEAD_UPDATE,
            ]),
            new StarterRoleDefinition(StarterRoleName::SystemStatusRead, [
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_RELEASE,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_READINESS,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_SCHEDULER,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_MODULE_ACTIVATION,
                CoreAuthorizationPermissionCatalog::SYSTEM_STATUS_VIEW,
                CoreAuthorizationPermissionCatalog::QUEUES_VIEW,
                CoreAuthorizationPermissionCatalog::ADMIN_QUEUES_INDEX,
                CoreAuthorizationPermissionCatalog::ADMIN_PULSE_VIEW,
            ]),
            new StarterRoleDefinition(StarterRoleName::SystemOperationsManage, [
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_RELEASE,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_READINESS,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_SCHEDULER,
                CoreAuthorizationPermissionCatalog::ADMIN_SYSTEM_STATUS_MODULE_ACTIVATION,
                CoreAuthorizationPermissionCatalog::SYSTEM_STATUS_VIEW,
                CoreAuthorizationPermissionCatalog::QUEUES_VIEW,
                CoreAuthorizationPermissionCatalog::QUEUES_MANAGE,
                CoreAuthorizationPermissionCatalog::ADMIN_QUEUES_INDEX,
                CoreAuthorizationPermissionCatalog::ADMIN_QUEUES_RETRY,
                CoreAuthorizationPermissionCatalog::ADMIN_PULSE_VIEW,
                CoreAuthorizationPermissionCatalog::ADMIN_RATE_LIMITS_INDEX,
                CoreAuthorizationPermissionCatalog::ADMIN_RATE_LIMITS_RESET,
                CoreAuthorizationPermissionCatalog::ADMIN_LOGS_INDEX,
                CoreAuthorizationPermissionCatalog::MODULE_ACTIVATION_VIEW,
                CoreAuthorizationPermissionCatalog::MODULE_ACTIVATION_UPDATE,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_INDEX,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_SHOW,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_GLOBAL_UPDATE,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_TEAM_UPDATE,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_TEAM_CLEAR,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_GLOBAL_SCHEDULE,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_TEAM_SCHEDULE,
                CoreAuthorizationPermissionCatalog::ADMIN_MODULES_SCHEDULE_CANCEL,
            ]),
            new StarterRoleDefinition(StarterRoleName::Administrator, $allPermissionNames),
        ];
    }
}
