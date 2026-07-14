<?php

declare(strict_types=1);

$appUrl = env('APP_URL', 'http://localhost');

if (! is_string($appUrl)) {
    throw new InvalidArgumentException('APP_URL must be a string.');
}

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim($appUrl, '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
