<?php

declare(strict_types=1);

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    'enabled' => env('TELESCOPE_ENABLED', in_array(env('APP_ENV'), ['local', 'development'], true)),

    'domain' => null,
    'path' => env('TELESCOPE_PATH', 'telescope'),

    'driver' => 'database',

    'storage' => [
        'database' => [
            'connection' => env('TELESCOPE_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
            'chunk' => 1000,
        ],
    ],

    'queue' => [
        'connection' => null,
        'queue' => null,
        'delay' => 10,
    ],

    'middleware' => [
        'web',
        Authorize::class,
    ],

    'only_paths' => [],

    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        '_boost*',
        '.well-known*',
    ],

    'ignore_commands' => [],

    'watchers' => [
        Watchers\BatchWatcher::class => true,

        Watchers\CacheWatcher::class => [
            'enabled' => true,
            'hidden' => [],
            'ignore' => [],
        ],

        Watchers\ClientRequestWatcher::class => [
            'enabled' => true,
            'ignore_hosts' => [],
        ],

        Watchers\CommandWatcher::class => [
            'enabled' => true,
            'ignore' => [],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => true,
            'always' => false,
        ],

        Watchers\EventWatcher::class => [
            'enabled' => true,
            'ignore' => [],
        ],

        Watchers\ExceptionWatcher::class => true,

        Watchers\GateWatcher::class => [
            'enabled' => true,
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],

        Watchers\JobWatcher::class => true,

        Watchers\LogWatcher::class => [
            'enabled' => true,
            'level' => 'error',
        ],

        Watchers\MailWatcher::class => true,

        Watchers\ModelWatcher::class => [
            'enabled' => true,
            'events' => ['eloquent.*'],
            'hydrations' => true,
        ],

        Watchers\NotificationWatcher::class => true,

        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'ignore_packages' => true,
            'ignore_paths' => [],
            'slow' => 100,
        ],

        Watchers\RedisWatcher::class => true,

        Watchers\RequestWatcher::class => [
            'enabled' => true,
            'size_limit' => 64,
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => true,
        Watchers\ViewWatcher::class => true,
    ],
];
