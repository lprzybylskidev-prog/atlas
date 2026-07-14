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

    'ui' => [
        'tailadmin' => [
            'pro_license_state' => env('ATLAS_TAILADMIN_PRO_LICENSE_STATE', 'not_confirmed'),
            'pro_license_confirmed_at' => env('ATLAS_TAILADMIN_PRO_LICENSE_CONFIRMED_AT') ?: null,
            'pro_license_confirmed_by' => env('ATLAS_TAILADMIN_PRO_LICENSE_CONFIRMED_BY') ?: null,
            'pro_redistribution_confirmed' => env('ATLAS_TAILADMIN_PRO_REDISTRIBUTION_CONFIRMED', false),
        ],
    ],

    'time' => [
        'business_timezone' => env('APP_TIMEZONE', 'Europe/Warsaw'),
        'technical_storage_timezone' => 'UTC',
    ],
];
