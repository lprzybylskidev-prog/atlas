<?php

declare(strict_types=1);

return [
    'currency' => [
        'default' => env('ATLAS_DEFAULT_CURRENCY', 'PLN'),
    ],

    'release' => [
        'version' => env('ATLAS_RELEASE_VERSION', '0.1.0-dev'),
        'id' => env('ATLAS_RELEASE_ID', 'local'),
    ],

    'time' => [
        'business_timezone' => env('APP_TIMEZONE', 'Europe/Warsaw'),
        'technical_storage_timezone' => 'UTC',
    ],
];
