<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Http\Controllers;

use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunInspector;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessRunner;
use App\Shared\Application\ManagedProcesses\DTOs\ManagedProcessRunSummary;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\RegisteredTables;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final readonly class AdminSearchController
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private ManagedProcessRunner $runner,
        private ManagedProcessRunInspector $runs,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $definition = RegisteredTables::get(RegisteredTables::SEARCH_INDEXES);
        $indexes = array_map(fn (SearchIndexDescriptor $descriptor): array => $this->indexRow($descriptor), $this->indexes->all());
        $filters = $this->filters($request, $indexes);
        $filteredIndexes = $this->filteredIndexes($indexes, $filters);
        $result = $this->tableResult($request, $definition, $filteredIndexes);
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;
        $rebuilds = $this->recentRebuildRuns();
        $readiness = $this->meilisearchReadiness();

        return Inertia::render('Admin/Search/Index', [
            'indexes' => $result->rows,
            'summary' => [
                'indexes' => count($result->filteredRows),
                'sensitive' => count(array_filter($result->filteredRows, static fn (array $index): bool => ($index['containsSensitiveData'] ?? false) === true)),
                'recentRebuilds' => count($rebuilds),
                'activeRebuilds' => count(array_filter($rebuilds, static fn (array $run): bool => in_array($run['status'] ?? '', ['draft', 'queued', 'running', 'waiting'], true))),
                'visibleIndexes' => $result->total,
            ],
            'filterOptions' => $this->filterOptions($indexes),
            'readiness' => $readiness,
            'recentRebuilds' => $rebuilds,
            'rebuildConfirmation' => 'REBUILD SEARCH',
            'table' => $table,
        ]);
    }

    public function rebuild(Request $request): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate([
            'confirmation' => ['required', 'string', 'in:REBUILD SEARCH'],
            'module_key' => ['nullable', 'string', 'max:120'],
            'index_key' => ['nullable', 'string', 'max:180'],
        ]));

        try {
            $runPublicId = $this->runner->start(
                processKey: SearchRebuildProcess::KEY,
                sourceType: 'manual',
                input: $this->inputSnapshot($validated),
                actorPublicId: $this->actorPublicId($request),
                teamPublicId: $this->teamPublicId($request),
            );
        } catch (RuntimeException) {
            return redirect()->route('admin.search.index')->with('flash.messages', [
                FlashMessage::error('flash.search.rebuild_failed'),
            ]);
        }

        return redirect()->route('admin.managed-processes.show', $runPublicId)->with('flash.messages', [
            FlashMessage::success('flash.search.rebuild_started'),
        ]);
    }

    /**
     * @return array<string, scalar|list<string>>
     */
    private function indexRow(SearchIndexDescriptor $descriptor): array
    {
        return [
            'key' => $descriptor->key,
            'moduleKey' => $descriptor->moduleKey,
            'stableAlias' => $descriptor->stableAlias,
            'searchableFields' => $descriptor->searchableFields,
            'filterableFields' => $descriptor->filterableFields,
            'sortableFields' => $descriptor->sortableFields,
            'containsSensitiveData' => $descriptor->containsSensitiveData,
            'supportsDeletion' => $descriptor->supportsDeletion,
            'supportsAnonymization' => $descriptor->supportsAnonymization,
        ];
    }

    /**
     * @param  list<array<string, scalar|list<string>>>  $rows
     */
    private function tableResult(Request $request, TableDefinition $definition, array $rows): TableResult
    {
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);

        return $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
    }

    /**
     * @param  list<array<string, scalar|list<string>>>  $rows
     * @return array{module: string, sensitivity: string, deletion: string, anonymization: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'module' => $this->oneOf($request->query('module'), $this->allOr($this->uniqueValues($rows, 'moduleKey'))),
            'sensitivity' => $this->oneOf($request->query('sensitivity'), ['all', 'sensitive', 'non_sensitive']),
            'deletion' => $this->oneOf($request->query('deletion'), ['all', 'supported', 'unsupported']),
            'anonymization' => $this->oneOf($request->query('anonymization'), ['all', 'supported', 'unsupported']),
        ];
    }

    /**
     * @param  list<array<string, scalar|list<string>>>  $rows
     * @return array{modules: list<string>}
     */
    private function filterOptions(array $rows): array
    {
        return [
            'modules' => $this->uniqueValues($rows, 'moduleKey'),
        ];
    }

    /**
     * @param  list<array<string, scalar|list<string>>>  $rows
     * @param  array{module: string, sensitivity: string, deletion: string, anonymization: string}  $filters
     * @return list<array<string, scalar|list<string>>>
     */
    private function filteredIndexes(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['module'] !== 'all' && ($row['moduleKey'] ?? null) !== $filters['module']) {
                return false;
            }

            if ($filters['sensitivity'] === 'sensitive' && ($row['containsSensitiveData'] ?? false) !== true) {
                return false;
            }

            if ($filters['sensitivity'] === 'non_sensitive' && ($row['containsSensitiveData'] ?? false) !== false) {
                return false;
            }

            if ($filters['deletion'] === 'supported' && ($row['supportsDeletion'] ?? false) !== true) {
                return false;
            }

            if ($filters['deletion'] === 'unsupported' && ($row['supportsDeletion'] ?? false) !== false) {
                return false;
            }

            if ($filters['anonymization'] === 'supported' && ($row['supportsAnonymization'] ?? false) !== true) {
                return false;
            }

            if ($filters['anonymization'] === 'unsupported' && ($row['supportsAnonymization'] ?? false) !== false) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<string, scalar|null|array<string, bool|int|string|null>>
     */
    private function meilisearchReadiness(): array
    {
        $host = Config::string('scout.meilisearch.host', '');
        $critical = Config::boolean('atlas.operations.health.meilisearch_critical', false);

        return [
            'key' => 'meilisearch',
            'label' => 'Meilisearch',
            'status' => $host === '' ? ($critical ? 'unhealthy' : 'degraded') : 'healthy',
            'blocking' => $critical,
            'metadata' => [
                'critical' => $critical,
                'configured' => $host !== '',
            ],
        ];
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function recentRebuildRuns(): array
    {
        return array_map(
            static fn (ManagedProcessRunSummary $run): array => [
                'publicId' => $run->publicId,
                'status' => $run->status,
                'currentStage' => $run->currentStage,
                'progressCurrent' => $run->progressCurrent,
                'progressTotal' => $run->progressTotal,
                'progressLabel' => $run->progressLabel,
                'createdAt' => $run->createdAt,
                'startedAt' => $run->startedAt,
                'finishedAt' => $run->finishedAt,
            ],
            $this->runs->recentRunsForProcess(SearchRebuildProcess::KEY, 8),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>|null
     */
    private function inputSnapshot(array $validated): ?array
    {
        $input = [];

        foreach (['module_key', 'index_key'] as $key) {
            $value = $validated[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $input[$key] = trim($value);
            }
        }

        return $input === [] ? null : $input;
    }

    private function actorPublicId(Request $request): ?string
    {
        $publicId = data_get($request->user(), 'public_id');

        return is_string($publicId) ? $publicId : null;
    }

    private function teamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) ? $teamPublicId : null;
    }

    /**
     * @param  list<array<string, scalar|list<string>>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                $values[] = (string) $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return ['all', ...$values];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        $normalized = is_scalar($value) ? (string) $value : 'all';

        return in_array($normalized, $allowed, true) ? $normalized : 'all';
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
