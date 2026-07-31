<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use App\Shared\Infrastructure\Queues\FailedJobAdminRows;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminFailedJobController
{
    public function __construct(
        private AuditRecorder $audit,
        private OperationalModuleGuard $modules,
        private FailedJobAdminRows $rows,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::FAILED_JOBS);
        $filters = $this->filters($request);
        $allRows = $this->rows->rows();
        $filteredRows = $this->filteredRows($allRows, $filters);
        $result = $this->tableResult($request, $definition, $filteredRows);
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Queues/Index', [
            'jobs' => $result->rows,
            'jobDetails' => $result->rows,
            'summary' => $this->summary($filteredRows),
            'queueOperations' => $this->queueOperations(),
            'filterOptions' => $this->filterOptions($allRows),
            'table' => $table,
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $validated = $this->validatedRetry($request);
        $uuids = $validated['uuids'];

        if (count($uuids) > 1 && $validated['confirmation'] !== 'RETRY') {
            return redirect()->route('admin.queues.index')->with('flash.messages', [
                FlashMessage::error('flash.queues.retry_typed_confirmation_required'),
            ]);
        }

        $jobs = DB::table(DatabaseTable::FAILED_JOBS)
            ->whereIn('uuid', $uuids)
            ->orderBy('failed_at')
            ->get(['uuid', 'connection', 'queue', 'payload', 'failed_at'])
            ->all();

        if (count($jobs) !== count($uuids)) {
            return redirect()->route('admin.queues.index')->with('flash.messages', [
                FlashMessage::error('flash.queues.retry_missing'),
            ]);
        }

        foreach ($jobs as $job) {
            $this->modules->ensureAllowed(
                moduleKey: $this->modules->moduleFromClassName($this->rows->jobClass($this->rows->jsonPayload($this->scalarString($job->payload ?? '')))),
                activeTeamPublicId: $request->hasSession() ? $this->nullableString($request->session()->get('active_team_public_id')) : null,
                userPublicId: $this->nullableString(data_get($request->user(), 'public_id')),
                permission: 'admin.queues.retry',
            );
        }

        $exitCode = Artisan::call('queue:retry', ['id' => $uuids]);

        if ($exitCode !== 0) {
            return redirect()->route('admin.queues.index')->with('flash.messages', [
                FlashMessage::error('flash.queues.retry_failed'),
            ]);
        }

        $this->recordRetryAudit($request, $jobs);

        return redirect()
            ->route('admin.queues.index')
            ->with('flash.messages', [
                FlashMessage::success(count($jobs) === 1 ? 'flash.queues.retry_single_queued' : 'flash.queues.retry_multiple_queued'),
            ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $validated = $this->validatedAcknowledge($request);
        $uuids = $validated['uuids'];
        $jobs = DB::table(DatabaseTable::FAILED_JOBS)
            ->whereIn('uuid', $uuids)
            ->orderBy('failed_at')
            ->get(['uuid', 'connection', 'queue', 'payload', 'failed_at'])
            ->all();

        if (count($jobs) !== count($uuids)) {
            return redirect()->route('admin.queues.index')->with('flash.messages', [
                FlashMessage::error('flash.queues.acknowledge_missing'),
            ]);
        }

        foreach ($jobs as $job) {
            $this->modules->ensureAllowed(
                moduleKey: $this->modules->moduleFromClassName($this->rows->jobClass($this->rows->jsonPayload($this->scalarString($job->payload ?? '')))),
                activeTeamPublicId: $request->hasSession() ? $this->nullableString($request->session()->get('active_team_public_id')) : null,
                userPublicId: $this->nullableString(data_get($request->user(), 'public_id')),
                permission: 'admin.queues.acknowledge',
            );
        }

        $this->acknowledgeJobs($request, $jobs, $validated['reason']);
        $this->recordAcknowledgeAudit($request, $jobs, $validated['reason']);

        return redirect()
            ->route('admin.queues.index')
            ->with('flash.messages', [
                FlashMessage::success(count($jobs) === 1 ? 'flash.queues.acknowledge_single' : 'flash.queues.acknowledge_multiple'),
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $visibleRows
     * @return array{failedCount: int, handledCount: int, visibleCount: int, queues: int, latestFailedAt: ?string, oldestFailedAt: ?string}
     */
    private function summary(array $visibleRows): array
    {
        $pendingAggregate = DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->whereNull('acknowledgements.failed_job_uuid')
            ->selectRaw('count(*) as failed_count')
            ->selectRaw('count(distinct failed_jobs.queue) as queues')
            ->selectRaw('max(failed_jobs.failed_at) as latest_failed_at')
            ->selectRaw('min(failed_jobs.failed_at) as oldest_failed_at')
            ->first();
        $handledCount = DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements')
            ->join(DatabaseTable::FAILED_JOBS.' as failed_jobs', 'failed_jobs.uuid', '=', 'acknowledgements.failed_job_uuid')
            ->count();

        $failedCount = is_object($pendingAggregate) && is_numeric($pendingAggregate->failed_count ?? null) ? (int) $pendingAggregate->failed_count : 0;

        return [
            'failedCount' => $failedCount,
            'handledCount' => (int) $handledCount,
            'visibleCount' => count($visibleRows),
            'queues' => is_object($pendingAggregate) && is_numeric($pendingAggregate->queues ?? null) ? (int) $pendingAggregate->queues : 0,
            'latestFailedAt' => is_object($pendingAggregate) && is_scalar($pendingAggregate->latest_failed_at ?? null) ? (string) $pendingAggregate->latest_failed_at : null,
            'oldestFailedAt' => is_object($pendingAggregate) && is_scalar($pendingAggregate->oldest_failed_at ?? null) ? (string) $pendingAggregate->oldest_failed_at : null,
        ];
    }

    /**
     * @return array{knownQueues: list<array{queue: string, configured: bool, failedJobs: int, handledJobs: int}>, totalFailedJobs: int, totalHandledJobs: int}
     */
    private function queueOperations(): array
    {
        $connection = Config::string('queue.default');
        $failedByQueue = DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->whereNull('acknowledgements.failed_job_uuid')
            ->selectRaw('failed_jobs.queue, count(*) as total')
            ->groupBy('failed_jobs.queue')
            ->pluck('total', 'queue');
        $handledByQueue = DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->join(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->selectRaw('failed_jobs.queue, count(*) as total')
            ->groupBy('failed_jobs.queue')
            ->pluck('total', 'queue');
        $configuredQueues = $this->configuredQueues($connection);
        $knownQueues = [];

        foreach (array_unique([...$configuredQueues, ...array_keys($failedByQueue->all()), ...array_keys($handledByQueue->all())]) as $queue) {
            if (! is_string($queue) || $queue === '') {
                continue;
            }

            $knownQueues[] = [
                'queue' => $queue,
                'configured' => in_array($queue, $configuredQueues, true),
                'failedJobs' => is_numeric($failedByQueue[$queue] ?? null) ? (int) $failedByQueue[$queue] : 0,
                'handledJobs' => is_numeric($handledByQueue[$queue] ?? null) ? (int) $handledByQueue[$queue] : 0,
            ];
        }

        usort($knownQueues, static fn (array $left, array $right): int => $left['queue'] <=> $right['queue']);

        return [
            'knownQueues' => $knownQueues,
            'totalFailedJobs' => (int) array_sum(array_column($knownQueues, 'failedJobs')),
            'totalHandledJobs' => (int) array_sum(array_column($knownQueues, 'handledJobs')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function tableResult(Request $request, TableDefinition $definition, array $rows): TableResult
    {
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);

        return $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
    }

    /**
     * @return array{connection: string, queue: string, handling: string, from: string, to: string}
     */
    private function filters(Request $request): array
    {
        $rows = $this->rows->rows();

        return [
            'connection' => $this->oneOf($request->query('connection'), $this->allOr($this->uniqueValues($rows, 'connection'))),
            'queue' => $this->oneOf($request->query('queue'), $this->allOr($this->uniqueValues($rows, 'queue'))),
            'handling' => $this->oneOf($request->query('handling', 'needs_attention'), ['needs_attention', 'handled', 'all']),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{connections: list<string>, queues: list<string>}
     */
    private function filterOptions(array $rows): array
    {
        return [
            'connections' => $this->uniqueValues($rows, 'connection'),
            'queues' => $this->uniqueValues($rows, 'queue'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{connection: string, queue: string, handling: string, from: string, to: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            foreach (['connection' => 'connection', 'queue' => 'queue'] as $column => $filter) {
                if ($filters[$filter] !== 'all' && ($row[$column] ?? null) !== $filters[$filter]) {
                    return false;
                }
            }

            if ($filters['handling'] !== 'all' && ($row['handlingStatus'] ?? null) !== $filters['handling']) {
                return false;
            }

            return self::dateRangeMatches(self::stringField($row, 'failedAt'), $filters['from'], $filters['to']);
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
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
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    private function dateFilter(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function dateRangeMatches(string $value, string $from, string $to): bool
    {
        if ($value === '') {
            return $from === '' && $to === '';
        }

        $date = substr($value, 0, 10);

        if ($from !== '' && $date < $from) {
            return false;
        }

        if ($to !== '' && $date > $to) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    private function configuredQueues(string $connection): array
    {
        $queues = [];
        $defaultQueue = Config::get('queue.connections.'.$connection.'.queue');

        if (is_string($defaultQueue) && $defaultQueue !== '') {
            $queues[] = $defaultQueue;
        }

        $supervisors = Config::get('horizon.defaults', []);

        if (is_array($supervisors)) {
            foreach ($supervisors as $supervisor) {
                if (! is_array($supervisor)) {
                    continue;
                }

                $configured = $supervisor['queue'] ?? null;

                if (is_string($configured) && $configured !== '') {
                    $queues[] = $configured;
                }

                if (is_array($configured)) {
                    foreach ($configured as $queue) {
                        if (is_string($queue) && $queue !== '') {
                            $queues[] = $queue;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($queues));
    }

    /**
     * @return array{uuids: list<string>, confirmation: ?string}
     */
    private function validatedRetry(Request $request): array
    {
        $validated = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:100'],
            'uuids.*' => ['required', 'uuid'],
            'confirmation' => ['nullable', 'string', 'max:20'],
        ]);

        $values = is_array($validated) ? $validated : [];
        $rawUuids = $values['uuids'] ?? [];
        $uuids = [];

        if (is_array($rawUuids)) {
            foreach ($rawUuids as $uuid) {
                if (is_string($uuid) && ! in_array($uuid, $uuids, true)) {
                    $uuids[] = $uuid;
                }
            }
        }

        return [
            'uuids' => $uuids,
            'confirmation' => is_string($values['confirmation'] ?? null) ? $values['confirmation'] : null,
        ];
    }

    /**
     * @return array{uuids: list<string>, reason: ?string}
     */
    private function validatedAcknowledge(Request $request): array
    {
        $validated = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:100'],
            'uuids.*' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $values = is_array($validated) ? $validated : [];
        $rawUuids = $values['uuids'] ?? [];
        $uuids = [];

        if (is_array($rawUuids)) {
            foreach ($rawUuids as $uuid) {
                if (is_string($uuid) && ! in_array($uuid, $uuids, true)) {
                    $uuids[] = $uuid;
                }
            }
        }

        return [
            'uuids' => $uuids,
            'reason' => is_string($values['reason'] ?? null) && trim($values['reason']) !== '' ? trim($values['reason']) : null,
        ];
    }

    /**
     * @param  array<int, object>  $jobs
     */
    private function acknowledgeJobs(Request $request, array $jobs, ?string $reason): void
    {
        $actorId = data_get($request->user(), 'id');
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        $now = now();

        foreach ($jobs as $job) {
            $uuid = $this->scalarString($job->uuid ?? '');
            $existing = DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS)->where('failed_job_uuid', $uuid)->exists();
            $values = [
                'acknowledged_by_user_id' => $actorId,
                'reason' => $reason,
                'acknowledged_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS)->where('failed_job_uuid', $uuid)->update($values);

                continue;
            }

            DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS)->insert($values + [
                'public_id' => (string) Str::ulid(),
                'failed_job_uuid' => $uuid,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, object>  $jobs
     */
    private function recordRetryAudit(Request $request, array $jobs): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $correlationId = $request->attributes->get('correlation_id');
        $uuids = [];
        $queues = [];

        foreach ($jobs as $job) {
            $uuids[] = $this->scalarString($job->uuid ?? '');
            $queues[] = $this->scalarString($job->queue ?? '');
        }

        $this->audit->record(new AuditEvent(
            module: 'authorization',
            action: count($jobs) === 1 ? 'queue.failed_job_retry' : 'queue.failed_jobs_retry',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: count($jobs) === 1 ? 'failed_job' : 'failed_jobs',
            aggregateType: 'queue',
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            correlationId: is_string($correlationId) ? $correlationId : null,
            metadata: [
                'count' => count($jobs),
                'uuids' => array_slice($uuids, 0, 100),
                'queues' => array_values(array_unique($queues)),
            ],
            security: true,
            securityCategory: SecurityAuditCategory::QueueOperations,
        ));
    }

    /**
     * @param  array<int, object>  $jobs
     */
    private function recordAcknowledgeAudit(Request $request, array $jobs, ?string $reason): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $correlationId = $request->attributes->get('correlation_id');
        $uuids = [];
        $queues = [];

        foreach ($jobs as $job) {
            $uuids[] = $this->scalarString($job->uuid ?? '');
            $queues[] = $this->scalarString($job->queue ?? '');
        }

        $this->audit->record(new AuditEvent(
            module: 'authorization',
            action: count($jobs) === 1 ? 'queue.failed_job_acknowledge' : 'queue.failed_jobs_acknowledge',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: count($jobs) === 1 ? 'failed_job' : 'failed_jobs',
            aggregateType: 'queue',
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            correlationId: is_string($correlationId) ? $correlationId : null,
            metadata: [
                'count' => count($jobs),
                'uuids' => array_slice($uuids, 0, 100),
                'queues' => array_values(array_unique($queues)),
                'reason' => $reason,
            ],
            security: true,
            securityCategory: SecurityAuditCategory::QueueOperations,
        ));
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
