<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator;

Breadcrumbs::for('login', function (Generator $breadcrumbs): void {
    $breadcrumbs->push(__('breadcrumbs.login'), route('login'));
});
