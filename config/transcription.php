<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('ATLAS_TRANSCRIPTION_ENABLED', false),
    'driver' => env('ATLAS_TRANSCRIPTION_DRIVER'),
    'credential' => env('ATLAS_TRANSCRIPTION_PROVIDER_CREDENTIAL'),
    'poll_seconds' => (int) env('ATLAS_TRANSCRIPTION_POLL_SECONDS', 15),
    'max_attempts' => (int) env('ATLAS_TRANSCRIPTION_MAX_ATTEMPTS', 3),
    'retry_backoff_seconds' => (int) env('ATLAS_TRANSCRIPTION_RETRY_BACKOFF_SECONDS', 30),
];
