<?php

declare(strict_types=1);

namespace App\Modules\Optional\Imports\Infrastructure\Fixtures;

use App\Modules\Optional\Imports\Application\Contracts\ImportFixtureBuilder;
use App\Modules\Optional\Imports\Infrastructure\Persistence\TableNames\ImportsDatabaseTable;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessFixtureBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DatabaseImportFixtureBuilder implements ImportFixtureBuilder
{
    public function __construct(private ManagedProcessFixtureBuilder $processes) {}

    public function provideVisibilityImport(string $actorUserPublicId, string $teamPublicId): void
    {
        if (DB::table(ImportsDatabaseTable::EXECUTIONS)->where('idempotency_key', 'e2e-import-csv')->exists()) {
            return;
        }

        $runId = $this->processes->provideImportVisibilityRun($actorUserPublicId, $teamPublicId);
        $executionId = (int) DB::table(ImportsDatabaseTable::EXECUTIONS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'process_run_id' => $runId,
            'import_key' => 'debtor-ledger-e2e',
            'source_type' => 'csv',
            'file_object_id' => null,
            'api_reference' => null,
            'external_reference' => 'e2e-ledger-feed',
            'mapping_snapshot' => json_encode(['mapping' => 'e2e'], JSON_THROW_ON_ERROR),
            'source_metadata' => json_encode(['rows' => 4], JSON_THROW_ON_ERROR),
            'statistics' => json_encode(['rows_total' => 4, 'rows_imported' => 2, 'rows_warned' => 2], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'e2e-import-csv',
            'idempotency_state' => 'completed',
            'created_at' => '2026-08-01 07:56:00+00',
            'updated_at' => '2026-08-01 07:58:00+00',
        ]);

        foreach ([3, 4] as $rowNumber) {
            DB::table(ImportsDatabaseTable::ROW_ERRORS)->insert([
                'public_id' => (string) Str::ulid(),
                'import_execution_id' => $executionId,
                'row_number' => $rowNumber,
                'field_name' => 'currency',
                'severity' => 'warning',
                'error_code' => 'currency.unsupported_e2e',
                'message' => 'E2E import accepts PLN rows only; row was skipped.',
                'safe_context' => json_encode(['currency' => 'EUR'], JSON_THROW_ON_ERROR),
                'created_at' => '2026-08-01 07:58:00+00',
                'updated_at' => '2026-08-01 07:58:00+00',
            ]);
        }
    }
}
