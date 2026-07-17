<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

Breadcrumbs::for('dashboard', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.app_dashboard'), route('dashboard'));
});

Breadcrumbs::for('notifications.index', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.app_dashboard'), route('dashboard'));
    $breadcrumbs->push('Notifications', route('notifications.index'));
});

Breadcrumbs::for('team.select', function (Generator $breadcrumbs): void {
    $breadcrumbs->push('Select team', route('team.select'));
});
