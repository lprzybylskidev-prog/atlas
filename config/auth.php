<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Infrastructure\Database\DatabaseTable;

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => DatabaseTable::PASSWORD_RESET_TOKENS,
            'expire' => 15,
            'throttle' => 15,
        ],
    ],

    'password_timeout' => 10800,
];
