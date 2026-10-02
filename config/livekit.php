<?php

declare(strict_types=1);

return [
    'rtc_enabled' => (bool) env('ATLAS_RTC_ENABLED', false),
    'egress_enabled' => (bool) env('ATLAS_RTC_EGRESS_ENABLED', false),
    'server_url' => env('LIVEKIT_URL', 'http://livekit:7880'),
    'client_url' => env('LIVEKIT_CLIENT_URL', 'ws://localhost:7880'),
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),
    'participant_token_ttl_seconds' => (int) env('LIVEKIT_PARTICIPANT_TOKEN_TTL_SECONDS', 300),
    'empty_room_timeout_seconds' => (int) env('LIVEKIT_EMPTY_ROOM_TIMEOUT_SECONDS', 900),
    'request_timeout_seconds' => (int) env('LIVEKIT_REQUEST_TIMEOUT_SECONDS', 5),
    'recording_template_url' => env('LIVEKIT_RECORDING_TEMPLATE_URL', 'http://app/rtc/recording-template'),
    'egress_output_directory' => env('LIVEKIT_EGRESS_OUTPUT_DIRECTORY', '/out'),
    'egress_staging_directory' => env('LIVEKIT_EGRESS_STAGING_DIRECTORY', '/var/lib/atlas/livekit-egress'),
    'ffmpeg_binary' => env('LIVEKIT_FFMPEG_BINARY', '/usr/bin/ffmpeg'),
    'health' => [
        'livekit' => [
            'host' => env('ATLAS_HEALTH_LIVEKIT_HOST', 'livekit'),
            'port' => (int) env('ATLAS_HEALTH_LIVEKIT_PORT', 7880),
            'critical' => (bool) env('ATLAS_HEALTH_LIVEKIT_CRITICAL', false),
        ],
        'egress' => [
            'host' => env('ATLAS_HEALTH_LIVEKIT_EGRESS_HOST', 'livekit-egress'),
            'port' => (int) env('ATLAS_HEALTH_LIVEKIT_EGRESS_PORT', 8081),
        ],
    ],
];
