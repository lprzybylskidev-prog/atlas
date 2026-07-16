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
    $breadcrumbs->push('Onboarding packages');
});

Breadcrumbs::for('admin.authorization.packages.create', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Onboarding packages');
    $breadcrumbs->push('Create');
});

Breadcrumbs::for('admin.authorization.packages.edit', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push('Onboarding packages');
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
