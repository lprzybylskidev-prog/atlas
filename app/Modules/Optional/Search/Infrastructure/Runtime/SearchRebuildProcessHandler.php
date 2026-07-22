<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessHandler;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessReporter;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunInspector;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchRebuildDocumentProvider;
use App\Modules\Optional\Search\Application\Rebuild\SearchIndexMaintenanceService;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;

final readonly class SearchRebuildProcessHandler implements ManagedProcessHandler
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private SearchIndexMaintenanceService $maintenance,
        private ManagedProcessReporter $reporter,
        private ManagedProcessRunInspector $runs,
        private OperationalModuleGuard $modules,
    ) {}

    public function processKey(): string
    {
        return SearchRebuildProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $this->modules->ensureAllowed('search');

        $descriptors = $this->indexes->all();
        $total = count($descriptors);
        $input = $this->runs->inputSnapshot($runPublicId);
        $moduleKey = $this->nullableString($input['module_key'] ?? null);
        $indexKey = $this->nullableString($input['index_key'] ?? null);

        $this->reporter->running($runPublicId, 'discovering_indexes', 0, $total, 'Discovering indexes');

        if ($total === 0) {
            $this->reporter->info($runPublicId, 'checkpoint', 'No search index descriptors are registered.', 'discovering_indexes');
            $this->reporter->succeeded($runPublicId, 'completed', 0, 0, 'No indexes registered', ['indexes' => 0]);

            return;
        }

        foreach ($descriptors as $offset => $descriptor) {
            $this->modules->ensureAllowed($descriptor->moduleKey);

            $this->reporter->info(
                $runPublicId,
                'checkpoint',
                'Search index descriptor is ready for rebuild orchestration.',
                'validating_indexes',
                [
                    'index_key' => $descriptor->key,
                    'module_key' => $descriptor->moduleKey,
                    'stable_alias' => $descriptor->stableAlias,
                ],
            );
            $this->reporter->running($runPublicId, 'validating_indexes', $offset + 1, $total, 'Validating indexes');
        }

        $this->reporter->running($runPublicId, 'rebuilding_indexes', 0, $total, 'Rebuilding indexes');
        $reports = $this->maintenance->rebuild($moduleKey, $indexKey, $this->documentProviders());

        foreach ($reports as $offset => $report) {
            $this->reporter->info($runPublicId, 'checkpoint', 'Search index rebuild validated and promoted.', 'rebuilding_indexes', $report->toSummary());
            $this->reporter->running($runPublicId, 'rebuilding_indexes', $offset + 1, max(1, count($reports)), 'Rebuilding indexes');
        }

        $this->reporter->succeeded($runPublicId, 'completed', count($reports), count($reports), 'Search rebuild completed', [
            'indexes' => count($reports),
            'discrepancies' => array_sum(array_map(static fn ($report): int => $report->discrepancy, $reports)),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<SearchRebuildDocumentProvider>
     */
    private function documentProviders(): array
    {
        $providers = [];

        foreach (app()->tagged('atlas.search_rebuild_document_providers') as $provider) {
            if ($provider instanceof SearchRebuildDocumentProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
