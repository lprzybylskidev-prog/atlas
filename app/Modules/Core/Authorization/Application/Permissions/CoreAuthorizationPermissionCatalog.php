<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Permissions;

use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class CoreAuthorizationPermissionCatalog implements ModulePermissionContribution
{
    public const DASHBOARD = 'dashboard';

    public const ADMIN_SYSTEM_STATUS = 'admin.system-status';

    public const ADMIN_AUTHORIZATION_ROLES = 'admin.authorization.roles.index';

    public const ADMIN_AUTHORIZATION_ROLES_CREATE = 'admin.authorization.roles.create';

    public const ADMIN_AUTHORIZATION_ROLES_STORE = 'admin.authorization.roles.store';

    public const ADMIN_AUTHORIZATION_ROLES_EDIT = 'admin.authorization.roles.edit';

    public const ADMIN_AUTHORIZATION_ROLES_UPDATE = 'admin.authorization.roles.update';

    public const ADMIN_AUTHORIZATION_ROLES_DELETE = 'admin.authorization.roles.destroy';

    public const ADMIN_AUTHORIZATION_PACKAGES = 'admin.authorization.packages.index';

    public const ADMIN_AUTHORIZATION_PACKAGES_CREATE = 'admin.authorization.packages.create';

    public const ADMIN_AUTHORIZATION_PACKAGES_STORE = 'admin.authorization.packages.store';

    public const ADMIN_AUTHORIZATION_PACKAGES_EDIT = 'admin.authorization.packages.edit';

    public const ADMIN_AUTHORIZATION_PACKAGES_UPDATE = 'admin.authorization.packages.update';

    public const ADMIN_AUTHORIZATION_PACKAGES_DELETE = 'admin.authorization.packages.destroy';

    public const ADMIN_AUTHORIZATION_PERMISSIONS = 'admin.authorization.permissions.index';

    public const ADMIN_TABLE_VIEWS_STORE = 'admin.table-views.store';

    public const ADMIN_TABLE_VIEWS_UPDATE = 'admin.table-views.update';

    public const ADMIN_TABLE_VIEWS_DELETE = 'admin.table-views.destroy';

    public const ADMIN_TABLE_VIEWS_COPY = 'admin.table-views.copy';

    public const ADMIN_TABLE_VIEWS_DEFAULT = 'admin.table-views.default';

    public const ROLES_VIEW = 'authorization.roles.view';

    public const ROLES_CREATE = 'authorization.roles.create';

    public const ROLES_UPDATE = 'authorization.roles.update';

    public const ROLES_DELETE = 'authorization.roles.delete';

    public const PERMISSIONS_VIEW = 'authorization.permissions.view';

    public const PERMISSIONS_ASSIGN = 'authorization.permissions.assign';

    public const ADMINISTRATOR_ROLE_DIFF = 'authorization.administrator-role.diff';

    public const ADMINISTRATOR_ROLE_UPDATE = 'authorization.administrator-role.update';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_UPDATE = 'settings.update';

    public const MODULE_ACTIVATION_VIEW = 'modules.activation.view';

    public const MODULE_ACTIVATION_UPDATE = 'modules.activation.update';

    public const SYSTEM_STATUS_VIEW = 'system.status.view';

    public const QUEUES_VIEW = 'system.queues.view';

    public const QUEUES_MANAGE = 'system.queues.manage';

    public const FILES_VIEW = 'files.view';

    public const FILES_MANAGE = 'files.manage';

    public const ADMIN_MODE_ENTER = 'admin-mode.enter';

    public const IMPERSONATION_START = 'impersonation.start';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::DASHBOARD, 'View the authenticated dashboard.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS, 'View the Admin system status screen.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES, 'View role administration.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_CREATE, 'Open role creation.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_STORE, 'Create roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_EDIT, 'Open role editing.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_UPDATE, 'Update roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_DELETE, 'Delete roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES, 'View onboarding package administration.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_CREATE, 'Open onboarding package creation.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_STORE, 'Create onboarding packages.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_EDIT, 'Open onboarding package editing.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_UPDATE, 'Update onboarding packages.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_DELETE, 'Delete onboarding packages.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PERMISSIONS, 'View permission administration.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_STORE, 'Create saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_UPDATE, 'Update saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_DELETE, 'Delete saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_COPY, 'Copy saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_DEFAULT, 'Set a default saved table view.'),
            new ModulePermissionDefinition(self::ROLES_VIEW, 'View roles.'),
            new ModulePermissionDefinition(self::ROLES_CREATE, 'Create roles.'),
            new ModulePermissionDefinition(self::ROLES_UPDATE, 'Update roles.'),
            new ModulePermissionDefinition(self::ROLES_DELETE, 'Delete roles.'),
            new ModulePermissionDefinition(self::PERMISSIONS_VIEW, 'View permissions.'),
            new ModulePermissionDefinition(self::PERMISSIONS_ASSIGN, 'Assign permissions.'),
            new ModulePermissionDefinition(self::ADMINISTRATOR_ROLE_DIFF, 'Preview administrator role permission updates.'),
            new ModulePermissionDefinition(self::ADMINISTRATOR_ROLE_UPDATE, 'Apply administrator role permission updates.'),
            new ModulePermissionDefinition(self::SETTINGS_VIEW, 'View settings.'),
            new ModulePermissionDefinition(self::SETTINGS_UPDATE, 'Update settings.'),
            new ModulePermissionDefinition(self::MODULE_ACTIVATION_VIEW, 'View module activation.'),
            new ModulePermissionDefinition(self::MODULE_ACTIVATION_UPDATE, 'Update module activation.'),
            new ModulePermissionDefinition(self::SYSTEM_STATUS_VIEW, 'View system status.'),
            new ModulePermissionDefinition(self::QUEUES_VIEW, 'View queues and failed jobs.'),
            new ModulePermissionDefinition(self::QUEUES_MANAGE, 'Manage queues and failed jobs.'),
            new ModulePermissionDefinition(self::FILES_VIEW, 'View files.'),
            new ModulePermissionDefinition(self::FILES_MANAGE, 'Manage files.'),
            new ModulePermissionDefinition(self::ADMIN_MODE_ENTER, 'Enter administrative mode.'),
            new ModulePermissionDefinition(self::IMPERSONATION_START, 'Start user impersonation.'),
        ];
    }
}
