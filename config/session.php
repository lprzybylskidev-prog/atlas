<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\DatabaseTable;

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => (bool) env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => DatabaseTable::SESSIONS,
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => 'atlas_session',
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),
    'secure' => false,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
    'serialization' => 'json',
];
