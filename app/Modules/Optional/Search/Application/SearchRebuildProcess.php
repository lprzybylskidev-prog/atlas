<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Application;

use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessPermissions;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\RetryPolicy;
use App\Modules\Optional\Search\Application\Permissions\SearchPermissionCatalog;

final class SearchRebuildProcess
{
    public const KEY = 'search.rebuild';

    public static function definition(): ProcessDefinition
    {
        return new ProcessDefinition(
            key: self::KEY,
            moduleKey: 'search',
            label: 'Search index rebuild',
            description: 'Rebuilds module-owned Meilisearch projections into fresh indexes before alias promotion.',
            scope: 'team',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'module_key' => ['type' => 'string'],
                    'index_key' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            permissions: new ProcessPermissions(
                view: SearchPermissionCatalog::ADMIN_INDEX,
                run: SearchPermissionCatalog::ADMIN_REBUILD,
                retry: SearchPermissionCatalog::ADMIN_REBUILD,
                cancel: SearchPermissionCatalog::ADMIN_REBUILD,
                schedule: SearchPermissionCatalog::ADMIN_REBUILD,
            ),
            queueName: 'search',
            executionMode: 'queued',
            concurrencyPolicy: 'one_active_per_team',
            parallelism: 1,
            retryPolicy: new RetryPolicy(retryable: true, maxAttempts: 2, backoffSeconds: 300),
            cancellationPolicy: 'safe_checkpoint',
            scheduleSupported: false,
            manualStartSupported: true,
            blocksModuleDeactivation: true,
        );
    }
}
