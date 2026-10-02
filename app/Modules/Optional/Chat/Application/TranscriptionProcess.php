<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Permissions\ChatPermissionCatalog;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessDefinition;
use App\Shared\Application\ManagedProcesses\DTOs\ProcessPermissions;
use App\Shared\Application\ManagedProcesses\DTOs\RetryPolicy;

final class TranscriptionProcess
{
    public const KEY = 'chat.meeting_transcription';

    public static function definition(): ProcessDefinition
    {
        return new ProcessDefinition(
            key: self::KEY,
            moduleKey: 'chat',
            label: 'Meeting transcription',
            description: 'Submits an eligible retained Meeting recording to the configured transcription provider and stores its result.',
            scope: 'team',
            inputSchema: [
                'type' => 'object',
                'required' => ['transcription_public_id'],
                'properties' => ['transcription_public_id' => ['type' => 'string']],
                'additionalProperties' => false,
            ],
            permissions: new ProcessPermissions(
                view: ChatPermissionCatalog::TRANSCRIPTION_SHOW,
                run: ChatPermissionCatalog::TRANSCRIPTION_STORE,
                retry: ChatPermissionCatalog::TRANSCRIPTION_STORE,
                cancel: ChatPermissionCatalog::TRANSCRIPTION_STORE,
                schedule: ChatPermissionCatalog::TRANSCRIPTION_STORE,
            ),
            queueName: 'managed-processes',
            executionMode: 'queued',
            concurrencyPolicy: 'parallel',
            parallelism: 1000,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 3, backoffSeconds: 30),
            cancellationPolicy: 'none',
            scheduleSupported: false,
            manualStartSupported: false,
            externalEffects: true,
            blocksModuleDeactivation: true,
        );
    }
}
