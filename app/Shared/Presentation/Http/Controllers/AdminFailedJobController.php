<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
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
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminFailedJobController
{
    public function __construct(
        private AuditRecorder $audit,
        private OperationalModuleGuard $modules,
        private FailedJobAdminRows $rows,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Queues/Index', [
            'jobs' => $this->rows->rows(),
            'summary' => $this->summary(),
            'queueOperations' => $this->queueOperations(),
            'exports' => AdminDataTableExportMeta::defaults(),
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

    /**
     * @return array{failedCount: int, visibleCount: int, queues: int, connections: int, latestFailedAt: ?string, oldestFailedAt: ?string}
     */
    private function summary(): array
    {
        $aggregate = DB::table(DatabaseTable::FAILED_JOBS)
            ->selectRaw('count(*) as failed_count')
            ->selectRaw('count(distinct queue) as queues')
            ->selectRaw('count(distinct connection) as connections')
            ->selectRaw('max(failed_at) as latest_failed_at')
            ->selectRaw('min(failed_at) as oldest_failed_at')
            ->first();

        $failedCount = is_object($aggregate) && is_numeric($aggregate->failed_count ?? null) ? (int) $aggregate->failed_count : 0;

        return [
            'failedCount' => $failedCount,
            'visibleCount' => min($failedCount, 200),
            'queues' => is_object($aggregate) && is_numeric($aggregate->queues ?? null) ? (int) $aggregate->queues : 0,
            'connections' => is_object($aggregate) && is_numeric($aggregate->connections ?? null) ? (int) $aggregate->connections : 0,
            'latestFailedAt' => is_object($aggregate) && is_scalar($aggregate->latest_failed_at ?? null) ? (string) $aggregate->latest_failed_at : null,
            'oldestFailedAt' => is_object($aggregate) && is_scalar($aggregate->oldest_failed_at ?? null) ? (string) $aggregate->oldest_failed_at : null,
        ];
    }

    /**
     * @return array{connection: string, driver: string, horizonPath: string|null, knownQueues: list<array{queue: string, configured: bool, failedJobs: int}>, totalFailedJobs: int, completedHistory: 'managed_processes'}
     */
    private function queueOperations(): array
    {
        $connection = Config::string('queue.default');
        $driver = Config::string('queue.connections.'.$connection.'.driver', $connection);
        $failedByQueue = DB::table(DatabaseTable::FAILED_JOBS)
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->pluck('total', 'queue');
        $configuredQueues = $this->configuredQueues($connection);
        $knownQueues = [];

        foreach (array_unique([...$configuredQueues, ...array_keys($failedByQueue->all())]) as $queue) {
            if (! is_string($queue) || $queue === '') {
                continue;
            }

            $knownQueues[] = [
                'queue' => $queue,
                'configured' => in_array($queue, $configuredQueues, true),
                'failedJobs' => is_numeric($failedByQueue[$queue] ?? null) ? (int) $failedByQueue[$queue] : 0,
            ];
        }

        usort($knownQueues, static fn (array $left, array $right): int => $left['queue'] <=> $right['queue']);

        return [
            'connection' => $connection,
            'driver' => $driver,
            'horizonPath' => $this->horizonPath(),
            'knownQueues' => $knownQueues,
            'totalFailedJobs' => (int) array_sum(array_column($knownQueues, 'failedJobs')),
            'completedHistory' => 'managed_processes',
        ];
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

    private function horizonPath(): ?string
    {
        $path = Config::get('horizon.path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return '/'.trim($path, '/');
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

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
