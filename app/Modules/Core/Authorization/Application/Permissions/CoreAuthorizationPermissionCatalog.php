<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Permissions;

use App\Modules\Core\Authorization\Application\Public\Permissions\CoreAuthorizationPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class CoreAuthorizationPermissionCatalog implements ModulePermissionContribution
{
    public const DASHBOARD = CoreAuthorizationPermissionNames::DASHBOARD;

    public const ADMIN_SYSTEM_STATUS = 'admin.system-status';

    public const ADMIN_SYSTEM_STATUS_RELEASE = 'admin.system-status.release';

    public const ADMIN_SYSTEM_STATUS_READINESS = 'admin.system-status.readiness';

    public const ADMIN_SYSTEM_STATUS_MODULES = 'admin.system-status.modules';

    public const ADMIN_SYSTEM_STATUS_SCHEDULER = 'admin.system-status.scheduler';

    public const ADMIN_SYSTEM_STATUS_MODULE_ACTIVATION = 'admin.system-status.module-activation';

    public const ADMIN_SYSTEM_STATUS_FAILED_JOBS = 'admin.system-status.failed-jobs';

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

    public const ADMIN_MODULES_INDEX = 'admin.modules.index';

    public const ADMIN_RATE_LIMITS_INDEX = 'admin.rate-limits.index';

    public const ADMIN_RATE_LIMITS_RESET = 'admin.rate-limits.reset';

    public const ADMIN_LOGS_INDEX = 'admin.logs.index';

    public const ADMIN_QUEUES_INDEX = 'admin.queues.index';

    public const ADMIN_QUEUES_RETRY = 'admin.queues.retry';

    public const ADMIN_QUEUES_ACKNOWLEDGE = 'admin.queues.acknowledge';

    public const ADMIN_PULSE_VIEW = 'admin.pulse.view';

    public const ADMIN_TELESCOPE_VIEW = 'admin.telescope.view';

    public const ADMIN_MODULES_SHOW = 'admin.modules.show';

    public const ADMIN_MODULES_TEAMS_CREATE = 'admin.modules.teams.create';

    public const ADMIN_MODULES_GLOBAL_UPDATE = 'admin.modules.global.update';

    public const ADMIN_MODULES_TEAM_UPDATE = 'admin.modules.team.update';

    public const ADMIN_MODULES_TEAM_CLEAR = 'admin.modules.team.clear';

    public const ADMIN_MODULES_GLOBAL_SCHEDULE = 'admin.modules.global.schedule';

    public const ADMIN_MODULES_TEAM_SCHEDULE = 'admin.modules.team.schedule';

    public const ADMIN_MODULES_SCHEDULE_CANCEL = 'admin.modules.schedules.cancel';

    public const ADMIN_TABLE_VIEWS_STORE = 'admin.table-views.store';

    public const ADMIN_TABLE_VIEWS_UPDATE = 'admin.table-views.update';

    public const ADMIN_TABLE_VIEWS_DELETE = 'admin.table-views.destroy';

    public const ADMIN_TABLE_VIEWS_COPY = 'admin.table-views.copy';

    public const ADMIN_TABLE_VIEWS_DEFAULT = 'admin.table-views.default';

    public const ADMIN_USERS_IMPERSONATE = 'admin.users.impersonate';

    public const ADMIN_USERS_IMPERSONATE_STORE = 'admin.users.impersonate.store';

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

    public const ADMIN_MODE_HIGH_RISK = 'admin-mode.high-risk';

    public const ADMIN_MODE_EXIT = 'admin-mode.exit';

    public const IMPERSONATION_START = 'impersonation.start';

    public const IMPERSONATION_SENSITIVE_OVERRIDE = 'impersonation.sensitive.override';

    public const IMPERSONATION_DESTROY = 'impersonation.destroy';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::DASHBOARD, 'View the authenticated dashboard.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS, 'View the Admin system status screen.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_RELEASE, 'View Admin release and deployment status.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_READINESS, 'View Admin readiness diagnostics.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_MODULES, 'View Admin module health and activation diagnostics.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_SCHEDULER, 'View Admin scheduler heartbeat status.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_MODULE_ACTIVATION, 'View Admin module activation scheduler diagnostics.'),
            new ModulePermissionDefinition(self::ADMIN_SYSTEM_STATUS_FAILED_JOBS, 'View Admin failed jobs dashboard diagnostics.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES, 'View role administration.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_CREATE, 'Open role creation.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_STORE, 'Create roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_EDIT, 'Open role editing.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_UPDATE, 'Update roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_ROLES_DELETE, 'Delete roles through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES, 'View preset administration.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_CREATE, 'Open preset creation.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_STORE, 'Create presets.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_EDIT, 'Open preset editing.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_UPDATE, 'Update presets.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PACKAGES_DELETE, 'Delete presets.'),
            new ModulePermissionDefinition(self::ADMIN_AUTHORIZATION_PERMISSIONS, 'View permission administration.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_INDEX, 'View module activation administration.'),
            new ModulePermissionDefinition(self::ADMIN_RATE_LIMITS_INDEX, 'View Admin rate-limit policies and rejection statistics.'),
            new ModulePermissionDefinition(self::ADMIN_RATE_LIMITS_RESET, 'Reset one concrete Admin rate-limit counter.'),
            new ModulePermissionDefinition(self::ADMIN_LOGS_INDEX, 'View curated Admin application logs.'),
            new ModulePermissionDefinition(self::ADMIN_QUEUES_INDEX, 'View Admin queues and failed jobs.'),
            new ModulePermissionDefinition(self::ADMIN_QUEUES_RETRY, 'Retry failed jobs through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_QUEUES_ACKNOWLEDGE, 'Mark failed jobs as handled through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_PULSE_VIEW, 'View Laravel Pulse internal performance dashboard.'),
            new ModulePermissionDefinition(self::ADMIN_TELESCOPE_VIEW, 'View Laravel Telescope local diagnostics dashboard.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_SHOW, 'View module activation details.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_TEAMS_CREATE, 'Open team module activation configuration.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_GLOBAL_UPDATE, 'Update global module activation.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_TEAM_UPDATE, 'Update team module activation overrides.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_TEAM_CLEAR, 'Clear team module activation overrides.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_GLOBAL_SCHEDULE, 'Schedule global module activation changes.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_TEAM_SCHEDULE, 'Schedule team module activation changes.'),
            new ModulePermissionDefinition(self::ADMIN_MODULES_SCHEDULE_CANCEL, 'Cancel scheduled module activation changes.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_STORE, 'Create saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_UPDATE, 'Update saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_DELETE, 'Delete saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_COPY, 'Copy saved table views.'),
            new ModulePermissionDefinition(self::ADMIN_TABLE_VIEWS_DEFAULT, 'Set a default saved table view.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_IMPERSONATE, 'Open user impersonation start screen.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_IMPERSONATE_STORE, 'Start user impersonation from Admin UI.'),
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
            new ModulePermissionDefinition(self::ADMIN_MODE_HIGH_RISK, 'Open high-risk administrative reauthentication.'),
            new ModulePermissionDefinition(self::ADMIN_MODE_EXIT, 'Exit administrative mode.'),
            new ModulePermissionDefinition(self::IMPERSONATION_START, 'Start user impersonation.'),
            new ModulePermissionDefinition(self::IMPERSONATION_SENSITIVE_OVERRIDE, 'Override the sensitive-account impersonation block after high-risk reauthentication.'),
            new ModulePermissionDefinition(self::IMPERSONATION_DESTROY, 'Exit user impersonation.'),
        ];
    }
}
