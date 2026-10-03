<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessDefinition;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessPermissions;
use App\Shared\Application\ManagedProcesses\DTOs\RetryPolicy;

final class ChatRetentionProcess
{
    public const KEY = 'chat.message_retention';

    public static function definition(): ProcessDefinition
    {
        return new ProcessDefinition(
            key: self::KEY, moduleKey: 'chat', label: 'Chat retention',
            description: 'Removes expired Chat messages, dependants, Search documents, and Files-owned attachments in bounded batches.',
            scope: 'team', inputSchema: ['type' => 'object', 'additionalProperties' => false],
            permissions: new ProcessPermissions(
                view: ChatPermissionCatalog::ADMIN_OPERATIONS_INDEX,
                run: ChatPermissionCatalog::ADMIN_RETENTION_RUN,
                retry: ChatPermissionCatalog::ADMIN_RETENTION_RUN,
                cancel: ChatPermissionCatalog::ADMIN_RETENTION_RUN,
                schedule: ChatPermissionCatalog::ADMIN_RETENTION_UPDATE,
            ),
            queueName: 'managed-processes', executionMode: 'queued', concurrencyPolicy: 'one_active_per_team', parallelism: 1,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 2, backoffSeconds: 300), cancellationPolicy: 'safe_checkpoint',
            scheduleSupported: false, manualStartSupported: true, externalEffects: true, blocksModuleDeactivation: true,
        );
    }
}
