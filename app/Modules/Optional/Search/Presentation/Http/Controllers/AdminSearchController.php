<?php

declare(strict_types=1);

namespace App\Modules\Optional\Search\Presentation\Http\Controllers;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\Search\Application\Contracts\SearchIndexRegistry;
use App\Modules\Optional\Search\Application\Public\DTOs\SearchIndexDescriptor;
use App\Modules\Optional\Search\Application\SearchRebuildProcess;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final readonly class AdminSearchController
{
    public function __construct(
        private SearchIndexRegistry $indexes,
        private ManagedProcessRunner $runner,
    ) {}

    public function index(): Response
    {
        $indexes = array_map(fn (SearchIndexDescriptor $descriptor): array => $this->indexRow($descriptor), $this->indexes->all());
        $rebuilds = $this->recentRebuildRuns();
        $readiness = $this->meilisearchReadiness();

        return Inertia::render('Admin/Search/Index', [
            'indexes' => $indexes,
            'summary' => [
                'indexes' => count($indexes),
                'sensitive' => count(array_filter($indexes, static fn (array $index): bool => ($index['containsSensitiveData'] ?? false) === true)),
                'recentRebuilds' => count($rebuilds),
                'activeRebuilds' => count(array_filter($rebuilds, static fn (array $run): bool => in_array($run['status'] ?? '', ['draft', 'queued', 'running', 'waiting'], true))),
            ],
            'readiness' => $readiness,
            'recentRebuilds' => $rebuilds,
            'rebuildConfirmation' => 'REBUILD SEARCH',
            'exports' => AdminDataTableExportMeta::defaults(),
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
            'description' => $host === ''
                ? 'Meilisearch host is not configured for Search projections.'
                : 'Meilisearch host is configured for Search projections. Full reachability is reported by Admin System Status.',
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
        return array_values(DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->where('process_key', SearchRebuildProcess::KEY)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['public_id', 'status', 'current_stage', 'progress_current', 'progress_total', 'progress_label', 'created_at', 'started_at', 'finished_at'])
            ->map(fn (object $row): array => [
                'publicId' => $this->string($row->public_id ?? null),
                'status' => $this->string($row->status ?? null),
                'currentStage' => $this->string($row->current_stage ?? null),
                'progressCurrent' => $this->int($row->progress_current ?? null),
                'progressTotal' => $this->nullableInt($row->progress_total ?? null),
                'progressLabel' => $this->string($row->progress_label ?? null),
                'createdAt' => $this->string($row->created_at ?? null),
                'startedAt' => $this->string($row->started_at ?? null),
                'finishedAt' => $this->string($row->finished_at ?? null),
            ])
            ->values()
            ->all());
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

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
