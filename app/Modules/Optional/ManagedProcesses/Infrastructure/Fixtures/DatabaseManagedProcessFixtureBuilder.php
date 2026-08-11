<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Infrastructure\Fixtures;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessFixtureBuilder;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseManagedProcessFixtureBuilder implements ManagedProcessFixtureBuilder
{
    public function __construct(private UserLookup $users, private TeamLookup $teams) {}

    public function provideImportVisibilityRun(string $actorUserPublicId, string $teamPublicId): int
    {
        $existing = DB::table(ManagedProcessesDatabaseTable::RUNS)
            ->where('process_key', 'e2e.imports.debtor-ledger')
            ->value('id');

        if (is_int($existing)) {
            return $existing;
        }

        $teamId = $this->teams->internalIdForPublicId($teamPublicId);
        $actorId = $this->users->internalIdForPublicId($actorUserPublicId);

        if ($teamId === null || $actorId === null) {
            throw new RuntimeException('The managed-process fixture requires an existing actor and team.');
        }

        $runId = (int) DB::table(ManagedProcessesDatabaseTable::RUNS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'process_key' => 'e2e.imports.debtor-ledger',
            'module_key' => 'imports',
            'scope' => 'team',
            'team_id' => $teamId,
            'actor_user_id' => $actorId,
            'source_type' => 'file_import',
            'input_snapshot' => json_encode(['source_type' => 'csv', 'idempotency_key' => 'e2e-import-csv'], JSON_THROW_ON_ERROR),
            'queue_connection' => 'sync',
            'queue_name' => 'imports',
            'job_identifier' => null,
            'status' => 'succeeded_with_warnings',
            'current_stage' => 'finished',
            'progress_current' => 4,
            'progress_total' => 4,
            'progress_label' => 'Import completed with warnings',
            'counters' => json_encode(['processed' => 4, 'success' => 2, 'info' => 1, 'warning' => 2, 'error' => 0, 'failed' => 0, 'skipped' => 2, 'retried' => 0], JSON_THROW_ON_ERROR),
            'correlation_id' => 'e2e-import-correlation',
            'causation_id' => null,
            'retry_of_run_id' => null,
            'queued_at' => '2026-08-01 07:56:00+00',
            'started_at' => '2026-08-01 07:57:00+00',
            'finished_at' => '2026-08-01 07:58:00+00',
            'failed_at' => null,
            'cancelled_at' => null,
            'retried_at' => null,
            'result_summary' => json_encode(['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2], JSON_THROW_ON_ERROR),
            'safe_error_summary' => null,
            'cancel_reason' => null,
            'created_at' => '2026-08-01 07:56:00+00',
            'updated_at' => '2026-08-01 07:58:00+00',
        ]);

        foreach ([
            ['info', 'message', 'queued', 'Process run queued.', null],
            ['info', 'stage', 'started', 'Process execution started.', null],
            ['warning', 'row_warning', 'validate', 'Skipped unsupported currency rows.', 'currency.unsupported_e2e'],
        ] as [$severity, $eventType, $stage, $message, $errorCode]) {
            DB::table(ManagedProcessesDatabaseTable::LOG_EVENTS)->insert([
                'public_id' => (string) Str::ulid(),
                'process_run_id' => $runId,
                'occurred_at' => '2026-08-01 07:58:00+00',
                'severity' => $severity,
                'event_type' => $eventType,
                'stage' => $stage,
                'message' => $message,
                'safe_context' => null,
                'row_number' => null,
                'entity_public_id' => null,
                'external_reference' => null,
                'source_reference' => null,
                'error_code' => $errorCode,
                'exception_class' => null,
                'retryable' => null,
                'correlation_id' => 'e2e-import-correlation',
                'created_at' => '2026-08-01 07:58:00+00',
                'updated_at' => '2026-08-01 07:58:00+00',
            ]);
        }

        return $runId;
    }
}
