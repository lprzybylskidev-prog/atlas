<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessDefinition;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessPermissions;
use App\Shared\Application\ManagedProcesses\DTOs\RetryPolicy;

final class MeetingRecordingRetentionProcess
{
    public const KEY = 'chat.meeting_recording_retention';

    public static function definition(): ProcessDefinition
    {
        return new ProcessDefinition(
            key: self::KEY,
            moduleKey: 'chat',
            label: 'Meeting recording retention',
            description: 'Removes expired Files-owned Meeting recordings and their dependent access grants in bounded batches.',
            scope: 'team',
            inputSchema: ['type' => 'object', 'additionalProperties' => false],
            permissions: new ProcessPermissions(
                view: ChatPermissionCatalog::ADMIN_OPERATIONS_INDEX,
                run: ChatPermissionCatalog::ADMIN_RECORDING_RETENTION_RUN,
                retry: ChatPermissionCatalog::ADMIN_RECORDING_RETENTION_RUN,
                cancel: ChatPermissionCatalog::ADMIN_RECORDING_RETENTION_RUN,
                schedule: ChatPermissionCatalog::ADMIN_RECORDING_RETENTION_UPDATE,
            ),
            queueName: 'managed-processes',
            executionMode: 'queued',
            concurrencyPolicy: 'one_active_per_team',
            parallelism: 1,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 2, backoffSeconds: 300),
            cancellationPolicy: 'safe_checkpoint',
            scheduleSupported: false,
            manualStartSupported: true,
            externalEffects: true,
            blocksModuleDeactivation: true,
        );
    }
}
