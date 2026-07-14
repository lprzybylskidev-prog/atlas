<?php

declare(strict_types=1);

use Diglactic\Breadcrumbs\Generator;
use Diglactic\Breadcrumbs\Manager;

return [
    'view' => 'breadcrumbs::bootstrap5',
    'files' => [
        base_path('routes/breadcrumbs/auth.php'),
        base_path('routes/breadcrumbs/application.php'),
        base_path('routes/breadcrumbs/admin.php'),
    ],
    'unnamed-route-exception' => true,
    'missing-route-bound-breadcrumb-exception' => true,
    'invalid-named-breadcrumb-exception' => true,
    'manager-class' => Manager::class,
    'generator-class' => Generator::class,
];
