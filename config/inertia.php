<?php

declare(strict_types=1);

return [
    'ssr' => [
        'enabled' => false,
        'runtime' => 'node',
        'ensure_runtime_exists' => false,
        'url' => 'http://127.0.0.1:13714',
        'ensure_bundle_exists' => false,
        'throw_on_error' => false,
    ],

    'pages' => [
        'ensure_pages_exist' => true,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'ts',
            'vue',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

    'expose_shared_prop_keys' => true,

    'history' => [
        'encrypt' => false,
    ],
];
