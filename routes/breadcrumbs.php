<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

Breadcrumbs::for('login', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.login'), route('login'));
});

Breadcrumbs::for('dashboard', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.dashboard'), route('dashboard'));
});

Breadcrumbs::for('admin.system-status', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.admin'));
    $breadcrumbs->push(__('breadcrumbs.dashboard'));
});
