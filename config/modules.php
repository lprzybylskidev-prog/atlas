<?php

declare(strict_types=1);

use App\Modules\Core\Identity\IdentityModule;
use App\Modules\Core\Users\UsersModule;

return [
    'deployed' => [
        IdentityModule::class,
        UsersModule::class,
    ],
];
