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

        'atlas_files' => [
            'driver' => 'local',
            'root' => storage_path('app/private/files'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        'atlas_files_s3' => [
            'driver' => 's3',
            'key' => env('ATLAS_FILES_S3_ACCESS_KEY_ID'),
            'secret' => env('ATLAS_FILES_S3_SECRET_ACCESS_KEY'),
            'region' => env('ATLAS_FILES_S3_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('ATLAS_FILES_S3_BUCKET'),
            'url' => env('ATLAS_FILES_S3_URL'),
            'endpoint' => env('ATLAS_FILES_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('ATLAS_FILES_S3_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'throw' => true,
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
