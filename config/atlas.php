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

    'security' => [
        'login_lock' => [
            'max_failed_attempts' => (int) env('ATLAS_LOGIN_LOCK_MAX_FAILED_ATTEMPTS', 10),
            'durations_seconds' => [
                (int) env('ATLAS_LOGIN_LOCK_FIRST_SECONDS', 900),
                (int) env('ATLAS_LOGIN_LOCK_SECOND_SECONDS', 1800),
                (int) env('ATLAS_LOGIN_LOCK_THIRD_SECONDS', 3600),
            ],
        ],
        'sessions' => [
            'max_lifetime_minutes' => (int) env('ATLAS_SESSION_MAX_LIFETIME_MINUTES', 720),
        ],
        'mfa' => [
            'requirements' => [
                'global' => (bool) env('ATLAS_MFA_REQUIRED_GLOBAL', false),
                'users' => array_filter(explode(',', (string) env('ATLAS_MFA_REQUIRED_USERS', ''))),
                'teams' => array_filter(explode(',', (string) env('ATLAS_MFA_REQUIRED_TEAMS', ''))),
                'permissions' => array_filter(explode(',', (string) env('ATLAS_MFA_REQUIRED_PERMISSIONS', ''))),
                'operations' => array_filter(explode(',', (string) env('ATLAS_MFA_REQUIRED_OPERATIONS', ''))),
            ],
        ],
        'webauthn' => [
            'rp_id' => env('ATLAS_WEBAUTHN_RP_ID', 'localhost'),
            'rp_name' => env('ATLAS_WEBAUTHN_RP_NAME', 'Atlas'),
            'timeout_ms' => (int) env('ATLAS_WEBAUTHN_TIMEOUT_MS', 60000),
        ],
        'rate_limits' => [
            'policies' => [
                'auth.login' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_AUTH_LOGIN_MAX_ATTEMPTS', 5),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_AUTH_LOGIN_DECAY_SECONDS', 60),
                    'key' => ['user', 'ip'],
                    'progressive_delay_seconds' => [60, 300, 900],
                    'temporary_lock_seconds' => 900,
                ],
                'auth.password-reset' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_AUTH_PASSWORD_RESET_MAX_ATTEMPTS', 3),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_AUTH_PASSWORD_RESET_DECAY_SECONDS', 3600),
                    'key' => ['user', 'ip'],
                    'progressive_delay_seconds' => [300, 900, 1800],
                    'temporary_lock_seconds' => 1800,
                ],
                'auth.mfa' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_AUTH_MFA_MAX_ATTEMPTS', 5),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_AUTH_MFA_DECAY_SECONDS', 300),
                    'key' => ['user', 'ip'],
                    'progressive_delay_seconds' => [60, 300, 900],
                    'temporary_lock_seconds' => 900,
                ],
                'api.default' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_API_DEFAULT_MAX_ATTEMPTS', 120),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_API_DEFAULT_DECAY_SECONDS', 60),
                    'key' => ['api_client', 'user', 'ip'],
                    'progressive_delay_seconds' => [],
                    'temporary_lock_seconds' => null,
                ],
                'api.sensitive' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_API_SENSITIVE_MAX_ATTEMPTS', 30),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_API_SENSITIVE_DECAY_SECONDS', 60),
                    'key' => ['api_client', 'user', 'ip'],
                    'progressive_delay_seconds' => [60, 300],
                    'temporary_lock_seconds' => 300,
                ],
                'exports.create' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_EXPORTS_CREATE_MAX_ATTEMPTS', 10),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_EXPORTS_CREATE_DECAY_SECONDS', 3600),
                    'key' => ['user', 'team', 'ip'],
                    'progressive_delay_seconds' => [300, 900],
                    'temporary_lock_seconds' => 900,
                ],
                'imports.create' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_IMPORTS_CREATE_MAX_ATTEMPTS', 10),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_IMPORTS_CREATE_DECAY_SECONDS', 3600),
                    'key' => ['user', 'team', 'ip'],
                    'progressive_delay_seconds' => [300, 900],
                    'temporary_lock_seconds' => 900,
                ],
                'admin.high-risk' => [
                    'max_attempts' => (int) env('ATLAS_RATE_LIMIT_ADMIN_HIGH_RISK_MAX_ATTEMPTS', 5),
                    'decay_seconds' => (int) env('ATLAS_RATE_LIMIT_ADMIN_HIGH_RISK_DECAY_SECONDS', 900),
                    'key' => ['user', 'team', 'ip'],
                    'progressive_delay_seconds' => [300, 900, 1800],
                    'temporary_lock_seconds' => 1800,
                ],
            ],
        ],
    ],
];
