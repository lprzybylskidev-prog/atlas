<?php

declare(strict_types=1);

return [
    'currency' => [
        'default' => env('ATLAS_DEFAULT_CURRENCY', 'PLN'),
    ],

    'release' => [
        'version' => env('ATLAS_RELEASE_VERSION', '0.1.0-dev'),
        'id' => env('ATLAS_RELEASE_ID', 'local'),
        'deployed_at' => env('ATLAS_RELEASE_DEPLOYED_AT') ?: null,
        'deployed_by' => env('ATLAS_RELEASE_DEPLOYED_BY') ?: null,
        'source' => env('ATLAS_RELEASE_SOURCE') ?: null,
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

    'operations' => [
        'scheduler_heartbeat_stale_seconds' => (int) env('ATLAS_SCHEDULER_HEARTBEAT_STALE_SECONDS', 180),
        'health' => [
            'meilisearch_critical' => (bool) env('ATLAS_HEALTH_MEILISEARCH_CRITICAL', false),
            'clamav' => [
                'critical' => (bool) env('ATLAS_HEALTH_CLAMAV_CRITICAL', false),
                'host' => env('ATLAS_HEALTH_CLAMAV_HOST') ?: null,
                'port' => (int) env('ATLAS_HEALTH_CLAMAV_PORT', 3310),
            ],
            'chromium' => [
                'critical' => (bool) env('ATLAS_HEALTH_CHROMIUM_CRITICAL', false),
                'binary' => env('ATLAS_HEALTH_CHROMIUM_BINARY') ?: null,
            ],
        ],
        'alerts' => [
            'enabled' => (bool) env('ATLAS_ALERTS_ENABLED', false),
            'dedupe_seconds' => (int) env('ATLAS_ALERTS_DEDUPE_SECONDS', 900),
            'throttle_seconds' => (int) env('ATLAS_ALERTS_THROTTLE_SECONDS', 300),
            'failed_jobs_threshold' => (int) env('ATLAS_ALERTS_FAILED_JOBS_THRESHOLD', 3),
            'email_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATLAS_ALERTS_EMAIL_TO', ''))))),
            'webhook_url' => env('ATLAS_ALERTS_WEBHOOK_URL') ?: null,
            'backup_failed' => (bool) env('ATLAS_ALERTS_BACKUP_FAILED', false),
            'integration_failed' => (bool) env('ATLAS_ALERTS_INTEGRATION_FAILED', false),
            'sentry_critical' => (bool) env('ATLAS_ALERTS_SENTRY_CRITICAL', false),
        ],
    ],

    'files' => [
        'disk' => env('ATLAS_FILES_DISK', env('APP_ENV') === 'production' ? 'atlas_files_s3' : 'atlas_files'),
        'scanner' => env('ATLAS_FILES_SCANNER', env('APP_ENV') === 'production' ? 'clamav' : 'fake'),
        'max_upload_bytes' => (int) env('ATLAS_FILES_MAX_UPLOAD_BYTES', 25 * 1024 * 1024),
        'large_upload_scan_threshold_bytes' => (int) env('ATLAS_FILES_LARGE_UPLOAD_SCAN_THRESHOLD_BYTES', 10 * 1024 * 1024),
        'scan_queue' => env('ATLAS_FILES_SCAN_QUEUE', 'files'),
        'large_scan_queue' => env('ATLAS_FILES_LARGE_SCAN_QUEUE', 'files-large'),
        'allowed_extensions' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATLAS_FILES_ALLOWED_EXTENSIONS', 'pdf,png,jpg,jpeg,txt,csv,xlsx,docx'))))),
        'allowed_mime_types' => array_values(array_filter(array_map('trim', explode(',', (string) env('ATLAS_FILES_ALLOWED_MIME_TYPES', 'application/pdf,image/png,image/jpeg,text/plain,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document'))))),
        'scan_max_attempts' => (int) env('ATLAS_FILES_SCAN_MAX_ATTEMPTS', 3),
        'temporary_ttl_minutes' => (int) env('ATLAS_FILES_TEMPORARY_TTL_MINUTES', 60),
        'temporary_scan_prefix' => env('ATLAS_FILES_TEMPORARY_SCAN_PREFIX', 'atlas-file-scan-'),
        'fake_scanner_result' => env('ATLAS_FILES_FAKE_SCANNER_RESULT', 'clean'),
        'clamav' => [
            'host' => env('ATLAS_FILES_CLAMAV_HOST', env('ATLAS_HEALTH_CLAMAV_HOST') ?: 'clamav'),
            'port' => (int) env('ATLAS_FILES_CLAMAV_PORT', env('ATLAS_HEALTH_CLAMAV_PORT', 3310)),
            'timeout_seconds' => (int) env('ATLAS_FILES_CLAMAV_TIMEOUT_SECONDS', 30),
        ],
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
