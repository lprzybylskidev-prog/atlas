<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

if (! function_exists('atlas_application_root')) {
    function atlas_application_root(Generator $breadcrumbs): void
    {
        $breadcrumbs->push(__('breadcrumbs.atlas'), route('dashboard'));
    }
}

if (! function_exists('atlas_admin_panel_root')) {
    function atlas_admin_panel_root(Generator $breadcrumbs): void
    {
        atlas_application_root($breadcrumbs);
        $breadcrumbs->push(__('breadcrumbs.admin'), route('admin.system-status'));
    }
}

if (! function_exists('atlas_breadcrumb_resource_action')) {
    function atlas_breadcrumb_resource_action(string $translationKey, mixed $identifier): string
    {
        $id = is_scalar($identifier) ? trim((string) $identifier) : '';
        $action = __($translationKey);

        return $id === ''
            ? $action
            : __('breadcrumbs.resource_action', ['action' => $action, 'id' => $id]);
    }
}

Breadcrumbs::for('admin.system-status', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.admin_dashboard'));
});

Breadcrumbs::for('admin.teams.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.teams'));
});

Breadcrumbs::for('admin.teams.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.teams'), route('admin.teams.index'));
    $breadcrumbs->push(__('breadcrumbs.teams_create'));
});

Breadcrumbs::for('admin.teams.edit', function (Generator $breadcrumbs, string $team): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.teams'), route('admin.teams.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.teams_edit', $team));
});

Breadcrumbs::for('admin.managers.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managers'));
});

Breadcrumbs::for('admin.managers.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managers'), route('admin.managers.index'));
    $breadcrumbs->push(__('breadcrumbs.managers_create'));
});

Breadcrumbs::for('admin.managers.edit', function (Generator $breadcrumbs, string $user): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managers'), route('admin.managers.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.managers_edit', $user));
});

Breadcrumbs::for('admin.work-time.summary.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_daily'), route('admin.work-time.summary.index'));
});

Breadcrumbs::for('admin.work-time.other-work.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('admin.work-time.other-work.index'));
});

Breadcrumbs::for('admin.work-time.other-work.categories.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('admin.work-time.other-work.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories'), route('admin.work-time.other-work.categories.index'));
});

Breadcrumbs::for('admin.work-time.other-work.categories.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('admin.work-time.other-work.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories'), route('admin.work-time.other-work.categories.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work_categories_create'));
});

Breadcrumbs::for('admin.work-time.work-sessions.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_sessions'), route('admin.work-time.work-sessions.index'));
});

Breadcrumbs::for('admin.work-time.work-sessions.show', function (Generator $breadcrumbs, string $session): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_sessions'), route('admin.work-time.work-sessions.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $session));
});

Breadcrumbs::for('admin.work-time.breaks.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_breaks'), route('admin.work-time.breaks.index'));
});

Breadcrumbs::for('admin.work-time.breaks.show', function (Generator $breadcrumbs, string $break): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_breaks'), route('admin.work-time.breaks.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $break));
});

Breadcrumbs::for('admin.work-time.corrections.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_corrections'), route('admin.work-time.corrections.index'));
});

Breadcrumbs::for('admin.work-time.corrections.manual-entry', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_corrections'), route('admin.work-time.corrections.index'));
    $breadcrumbs->push(__('breadcrumbs.work_time_manual_entry'));
});

Breadcrumbs::for('admin.work-time.other-work.show', function (Generator $breadcrumbs, string $otherWork): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_other_work'), route('admin.work-time.other-work.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $otherWork));
});

Breadcrumbs::for('admin.work-time.corrections.show', function (Generator $breadcrumbs, string $correction): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.work_time_operations'));
    $breadcrumbs->push(__('breadcrumbs.work_time_corrections'), route('admin.work-time.corrections.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $correction));
});

Breadcrumbs::for('admin.users.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.users'));
});

Breadcrumbs::for('admin.users.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.users'), route('admin.users.index'));
    $breadcrumbs->push(__('breadcrumbs.users_create'));
});

Breadcrumbs::for('admin.users.edit', function (Generator $breadcrumbs, string $user): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.users'), route('admin.users.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.users_edit', $user));
});

Breadcrumbs::for('admin.users.impersonate', function (Generator $breadcrumbs, string $user): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.users'), route('admin.users.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.users_impersonate', $user));
});

Breadcrumbs::for('admin.authorization.roles.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.roles'));
});

Breadcrumbs::for('admin.authorization.roles.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.roles'), route('admin.authorization.roles.index'));
    $breadcrumbs->push(__('breadcrumbs.roles_create'));
});

Breadcrumbs::for('admin.authorization.roles.edit', function (Generator $breadcrumbs, string $role): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.roles'), route('admin.authorization.roles.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.roles_edit', $role));
});

Breadcrumbs::for('admin.authorization.packages.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.packages'));
});

Breadcrumbs::for('admin.authorization.packages.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.packages'), route('admin.authorization.packages.index'));
    $breadcrumbs->push(__('breadcrumbs.packages_create'));
});

Breadcrumbs::for('admin.authorization.packages.edit', function (Generator $breadcrumbs, string $package): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.packages'), route('admin.authorization.packages.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.packages_edit', $package));
});

Breadcrumbs::for('admin.authorization.permissions.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.permissions'));
});

Breadcrumbs::for('admin.audit.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.audit'));
});

Breadcrumbs::for('admin.audit.impersonation.show', function (Generator $breadcrumbs, string $session): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.audit'), route('admin.audit.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.impersonation_session', $session));
});

Breadcrumbs::for('admin.audit.security-history.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.audit'), route('admin.audit.index'));
    $breadcrumbs->push(__('breadcrumbs.security_history'));
});

Breadcrumbs::for('admin.rate-limits.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.rate_limits'));
});

Breadcrumbs::for('admin.logs.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.logs'));
});

Breadcrumbs::for('admin.queues.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.queues'));
});

Breadcrumbs::for('admin.files.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.files'));
});

Breadcrumbs::for('admin.privacy-retention.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.privacy_retention'));
});

Breadcrumbs::for('admin.privacy-retention.legal-holds.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.privacy_retention'), route('admin.privacy-retention.index'));
    $breadcrumbs->push(__('breadcrumbs.privacy_legal_holds'));
});

Breadcrumbs::for('admin.privacy-retention.legal-holds.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.privacy_retention'), route('admin.privacy-retention.index'));
    $breadcrumbs->push(__('breadcrumbs.privacy_legal_holds'), route('admin.privacy-retention.legal-holds.index'));
    $breadcrumbs->push(__('breadcrumbs.privacy_legal_holds_create'));
});

Breadcrumbs::for('admin.privacy-retention.operations.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.privacy_retention'), route('admin.privacy-retention.index'));
    $breadcrumbs->push(__('breadcrumbs.privacy_operations'));
});

Breadcrumbs::for('admin.feature-flags.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.feature_flags'));
});

Breadcrumbs::for('admin.integrations.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.integrations'));
});

Breadcrumbs::for('admin.modules.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.modules'));
});

Breadcrumbs::for('admin.modules.show', function (Generator $breadcrumbs, string $module): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.modules'), route('admin.modules.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.details', $module));
});

Breadcrumbs::for('admin.modules.teams.create', function (Generator $breadcrumbs, string $module): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.modules'), route('admin.modules.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.module_team_configuration', request()->query('team') ?? $module));
});

Breadcrumbs::for('admin.managed-processes.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_runs'));
});

Breadcrumbs::for('admin.managed-processes.show', function (Generator $breadcrumbs, string $run): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_runs'), route('admin.managed-processes.index'));
    $breadcrumbs->push(atlas_breadcrumb_resource_action('breadcrumbs.managed_process_run', $run));
});

Breadcrumbs::for('admin.managed-processes.definitions.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_definitions'));
});

Breadcrumbs::for('admin.managed-processes.schedules.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedules'));
});

Breadcrumbs::for('admin.managed-processes.schedules.create', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedules'), route('admin.managed-processes.schedules.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedule_create'));
});

Breadcrumbs::for('admin.search.index', function (Generator $breadcrumbs): void {
    atlas_admin_panel_root($breadcrumbs);
    $breadcrumbs->push(__('breadcrumbs.search'));
});
