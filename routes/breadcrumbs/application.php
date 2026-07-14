<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

Breadcrumbs::for('dashboard', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.app_dashboard'), route('dashboard'));
});
