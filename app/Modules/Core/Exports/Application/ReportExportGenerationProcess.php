<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessPermissions;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\RetryPolicy;

final class ReportExportGenerationProcess
{
    public const KEY = 'exports.generate';

    public static function definition(): ProcessDefinition
    {
        return new ProcessDefinition(
            key: self::KEY,
            moduleKey: 'exports',
            label: 'Export generation',
            description: 'Generates one authorized report/export artifact from an immutable request snapshot.',
            scope: 'team',
            inputSchema: [
                'type' => 'object',
                'required' => ['export_request_public_id'],
                'properties' => [
                    'export_request_public_id' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            permissions: new ProcessPermissions(
                view: ReportsPermissionCatalog::ADMIN_INDEX,
                run: ReportsPermissionCatalog::REQUEST,
                retry: ReportsPermissionCatalog::REQUEST,
                cancel: ReportsPermissionCatalog::REQUEST,
                schedule: ReportsPermissionCatalog::ADMIN_INDEX,
            ),
            queueName: 'exports',
            executionMode: 'queued',
            concurrencyPolicy: 'one_active_per_actor',
            parallelism: 1,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 2, backoffSeconds: 300),
            cancellationPolicy: 'safe_checkpoint',
            scheduleSupported: false,
            manualStartSupported: false,
            blocksModuleDeactivation: true,
        );
    }
}
