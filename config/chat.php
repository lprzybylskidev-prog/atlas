<?php

declare(strict_types=1);

$recordingRetentionDays = env('ATLAS_CHAT_RECORDING_RETENTION_DAYS');

return [
    'recording_retention_days' => $recordingRetentionDays === null || $recordingRetentionDays === '' ? null : (int) $recordingRetentionDays,
    'recording_retention_batch_size' => (int) env('ATLAS_CHAT_RECORDING_RETENTION_BATCH_SIZE', 100),
];
