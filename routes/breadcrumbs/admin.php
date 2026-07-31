<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

Breadcrumbs::for('admin.system-status', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.admin_dashboard'));
});

Breadcrumbs::for('admin.teams.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.teams'));
});

Breadcrumbs::for('admin.teams.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.teams'));
    $breadcrumbs->push(__('breadcrumbs.teams_create'));
});

Breadcrumbs::for('admin.teams.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.teams'));
    $breadcrumbs->push(__('breadcrumbs.teams_edit'));
});

Breadcrumbs::for('admin.managers.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managers'));
});

Breadcrumbs::for('admin.managers.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managers'), route('admin.managers.index'));
    $breadcrumbs->push(__('breadcrumbs.managers_create'));
});

Breadcrumbs::for('admin.managers.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managers'), route('admin.managers.index'));
    $breadcrumbs->push(__('breadcrumbs.managers_edit'));
});

Breadcrumbs::for('admin.users.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.users'));
});

Breadcrumbs::for('admin.users.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.users'));
    $breadcrumbs->push(__('breadcrumbs.users_create'));
});

Breadcrumbs::for('admin.users.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.users'));
    $breadcrumbs->push(__('breadcrumbs.users_edit'));
});

Breadcrumbs::for('admin.users.impersonate', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.users'), route('admin.users.index'));
    $breadcrumbs->push(__('breadcrumbs.users_impersonate'));
});

Breadcrumbs::for('admin.authorization.roles.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.roles'));
});

Breadcrumbs::for('admin.authorization.roles.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.roles'));
    $breadcrumbs->push(__('breadcrumbs.roles_create'));
});

Breadcrumbs::for('admin.authorization.roles.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.roles'));
    $breadcrumbs->push(__('breadcrumbs.roles_edit'));
});

Breadcrumbs::for('admin.authorization.packages.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.packages'));
});

Breadcrumbs::for('admin.authorization.packages.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.packages'));
    $breadcrumbs->push(__('breadcrumbs.packages_create'));
});

Breadcrumbs::for('admin.authorization.packages.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.packages'));
    $breadcrumbs->push(__('breadcrumbs.packages_edit'));
});

Breadcrumbs::for('admin.authorization.permissions.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.permissions'));
});

Breadcrumbs::for('admin.audit.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.audit'));
});

Breadcrumbs::for('admin.audit.impersonation.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.audit'), route('admin.audit.index'));
    $breadcrumbs->push(__('breadcrumbs.impersonation_session'));
});

Breadcrumbs::for('admin.audit.security-history.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.audit'), route('admin.audit.index'));
    $breadcrumbs->push(__('breadcrumbs.security_history'));
});

Breadcrumbs::for('admin.rate-limits.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.rate_limits'));
});

Breadcrumbs::for('admin.logs.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.logs'));
});

Breadcrumbs::for('admin.queues.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Queues');
});

Breadcrumbs::for('admin.files.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.files'));
});

Breadcrumbs::for('admin.feature-flags.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.feature_flags'));
});

Breadcrumbs::for('admin.integrations.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.integrations'));
});

Breadcrumbs::for('admin.modules.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.modules'));
});

Breadcrumbs::for('admin.modules.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.modules'), route('admin.modules.index'));
    $breadcrumbs->push(__('breadcrumbs.details'));
});

Breadcrumbs::for('admin.modules.teams.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.modules'), route('admin.modules.index'));
    $breadcrumbs->push(__('breadcrumbs.module_team_configuration'));
});

Breadcrumbs::for('admin.managed-processes.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_runs'));
});

Breadcrumbs::for('admin.managed-processes.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_runs'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_run'));
});

Breadcrumbs::for('admin.managed-processes.definitions.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_definitions'));
});

Breadcrumbs::for('admin.managed-processes.schedules.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedules'));
});

Breadcrumbs::for('admin.managed-processes.schedules.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.managed_processes'), route('admin.managed-processes.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedules'), route('admin.managed-processes.schedules.index'));
    $breadcrumbs->push(__('breadcrumbs.managed_process_schedule_create'));
});

Breadcrumbs::for('admin.search.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.search'));
});
