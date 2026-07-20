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
    $breadcrumbs->push('Teams');
});

Breadcrumbs::for('admin.teams.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Teams');
    $breadcrumbs->push('Create');
});

Breadcrumbs::for('admin.teams.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Teams');
    $breadcrumbs->push('Edit');
});

Breadcrumbs::for('admin.managers.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managers');
});

Breadcrumbs::for('admin.users.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Users');
});

Breadcrumbs::for('admin.users.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Users');
    $breadcrumbs->push('Create');
});

Breadcrumbs::for('admin.users.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Users');
    $breadcrumbs->push('Edit');
});

Breadcrumbs::for('admin.authorization.roles.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Roles');
});

Breadcrumbs::for('admin.authorization.roles.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Roles');
    $breadcrumbs->push('Create');
});

Breadcrumbs::for('admin.authorization.roles.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Roles');
    $breadcrumbs->push('Edit');
});

Breadcrumbs::for('admin.authorization.packages.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Presets');
});

Breadcrumbs::for('admin.authorization.packages.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Presets');
    $breadcrumbs->push('Create');
});

Breadcrumbs::for('admin.authorization.packages.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Presets');
    $breadcrumbs->push('Edit');
});

Breadcrumbs::for('admin.authorization.permissions.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Permissions');
});

Breadcrumbs::for('admin.audit.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Audit');
});

Breadcrumbs::for('admin.audit.impersonation.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Audit', route('admin.audit.index'));
    $breadcrumbs->push('Impersonation session');
});

Breadcrumbs::for('admin.audit.security-history.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Audit', route('admin.audit.index'));
    $breadcrumbs->push('Security history');
});

Breadcrumbs::for('admin.rate-limits.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Rate limits');
});

Breadcrumbs::for('admin.logs.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Application logs');
});

Breadcrumbs::for('admin.queues.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Queues');
});

Breadcrumbs::for('admin.files.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Files');
});

Breadcrumbs::for('admin.modules.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Modules');
});

Breadcrumbs::for('admin.modules.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Modules');
    $breadcrumbs->push('Details');
});

Breadcrumbs::for('admin.managed-processes.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managed processes');
});

Breadcrumbs::for('admin.managed-processes.show', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managed processes', route('admin.managed-processes.index'));
    $breadcrumbs->push('Run details');
});

Breadcrumbs::for('admin.managed-processes.imports.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managed processes', route('admin.managed-processes.index'));
    $breadcrumbs->push('Imports');
});

Breadcrumbs::for('admin.managed-processes.definitions.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managed processes', route('admin.managed-processes.index'));
    $breadcrumbs->push('Definitions');
});

Breadcrumbs::for('admin.managed-processes.schedules.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Managed processes', route('admin.managed-processes.index'));
    $breadcrumbs->push('Schedules');
});

Breadcrumbs::for('admin.imports.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Imports');
});

Breadcrumbs::for('admin.search.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Search');
});
