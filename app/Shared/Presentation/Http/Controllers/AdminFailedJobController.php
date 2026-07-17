<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminFailedJobController
{
    public function __construct(
        private AuditRecorder $audit,
        private OperationalModuleGuard $modules,
    ) {}

    public function index(): Response
    {
        $jobs = DB::table(DatabaseTable::FAILED_JOBS)
            ->orderByDesc('failed_at')
            ->limit(200)
            ->get(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(fn (object $row): array => $this->jobRow($row))
            ->values()
            ->all();

        return Inertia::render('Admin/Queues/Index', [
            'jobs' => $jobs,
            'summary' => $this->summary(),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $validated = $this->validatedRetry($request);
        $uuids = $validated['uuids'];

        if (count($uuids) > 1 && $validated['confirmation'] !== 'RETRY') {
            return redirect()->route('admin.queues.index')->with('error', 'Mass retry requires typed confirmation.');
        }

        $jobs = DB::table(DatabaseTable::FAILED_JOBS)
            ->whereIn('uuid', $uuids)
            ->orderBy('failed_at')
            ->get(['uuid', 'connection', 'queue', 'failed_at'])
            ->all();

        if (count($jobs) !== count($uuids)) {
            return redirect()->route('admin.queues.index')->with('error', 'One or more failed jobs no longer exist.');
        }

        foreach ($jobs as $job) {
            $this->modules->ensureAllowed(
                moduleKey: $this->modules->moduleFromClassName($this->jobClass($this->jsonPayload($this->scalarString($job->payload ?? '')))),
                activeTeamPublicId: $request->hasSession() ? $this->nullableString($request->session()->get('active_team_public_id')) : null,
                userPublicId: $this->nullableString(data_get($request->user(), 'public_id')),
                permission: 'admin.queues.retry',
            );
        }

        $exitCode = Artisan::call('queue:retry', ['id' => $uuids]);

        if ($exitCode !== 0) {
            return redirect()->route('admin.queues.index')->with('error', 'Failed jobs could not be retried.');
        }

        $this->recordRetryAudit($request, $jobs);

        return redirect()
            ->route('admin.queues.index')
            ->with('success', count($jobs) === 1 ? 'Failed job was queued for retry.' : 'Failed jobs were queued for retry.');
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
     * @return array{uuid: string, connection: string, queue: string, failedAt: string, displayName: string, jobClass: string, exceptionType: string, exceptionMessage: string, payload: string, exception: string}
     */
    private function jobRow(object $row): array
    {
        $payload = $this->scalarString($row->payload ?? '');
        $exception = $this->scalarString($row->exception ?? '');
        $payloadData = $this->jsonPayload($payload);

        return [
            'uuid' => $this->scalarString($row->uuid ?? ''),
            'connection' => $this->scalarString($row->connection ?? ''),
            'queue' => $this->scalarString($row->queue ?? ''),
            'failedAt' => $this->scalarString($row->failed_at ?? ''),
            'displayName' => $this->displayName($payloadData),
            'jobClass' => $this->jobClass($payloadData),
            'exceptionType' => $this->exceptionType($exception),
            'exceptionMessage' => $this->exceptionMessage($exception),
            'payload' => $this->prettyJson($payload),
            'exception' => $exception,
        ];
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
            securityCategory: 'queue_operations',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $this->stringKeyedArray($decoded) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function displayName(array $payload): string
    {
        $value = $payload['displayName'] ?? $payload['job'] ?? null;

        return is_scalar($value) ? (string) $value : 'Unknown job';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jobClass(array $payload): string
    {
        $commandName = data_get($payload, 'data.commandName');

        if (is_string($commandName) && $commandName !== '') {
            return $commandName;
        }

        return $this->displayName($payload);
    }

    private function exceptionType(string $exception): string
    {
        if (preg_match('/^([A-Za-z0-9_\\\\]+):/', $exception, $matches) === 1) {
            return $matches[1];
        }

        return 'Exception';
    }

    private function exceptionMessage(string $exception): string
    {
        $firstLine = strtok($exception, "\n");

        if (! is_string($firstLine) || trim($firstLine) === '') {
            return 'No exception message recorded.';
        }

        return mb_substr($firstLine, 0, 700);
    }

    private function prettyJson(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return $payload;
        }

        return is_string($encoded) ? $encoded : $payload;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
