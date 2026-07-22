<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Presentation\Http\Controllers;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final readonly class AdminManagedProcessesController
{
    public function __construct(
        private ProcessDefinitionRegistry $definitions,
        private ManagedProcessRunner $runner,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/ManagedProcesses/Runs', [
            'runs' => $this->runs(),
            'summary' => $this->summary(),
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function imports(): Response
    {
        return Inertia::render('Admin/ManagedProcesses/Imports', [
            'importExecutions' => $this->importExecutions(),
            'summary' => $this->summary(),
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function definitions(): Response
    {
        return Inertia::render('Admin/ManagedProcesses/Definitions', [
            'definitions' => array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all()),
            'summary' => [
                'definitions' => count($this->definitions->all()),
                'schedulable' => count(array_filter($this->definitions->all(), fn (ProcessDefinition $definition): bool => $definition->scheduleSupported)),
                'manual' => count(array_filter($this->definitions->all(), fn (ProcessDefinition $definition): bool => $definition->manualStartSupported)),
            ],
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function show(string $run): Response
    {
        $record = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->leftJoin(DatabaseTable::USERS, 'process_runs.actor_user_id', '=', 'users.id')
            ->leftJoin(DatabaseTable::TEAMS, 'process_runs.team_id', '=', 'teams.id')
            ->where('process_runs.public_id', $run)
            ->first([
                'process_runs.*',
                'users.public_id as actor_public_id',
                'users.email as actor_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
            ]);

        abort_if(! is_object($record), 404);

        return Inertia::render('Admin/ManagedProcesses/Show', [
            'run' => $this->runRow($record),
            'logs' => $this->logs($this->intValue($record->id ?? null)),
            'importExecution' => $this->importExecution($this->intValue($record->id ?? null)),
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate([
            'process_key' => ['required', 'string'],
            'source_type' => ['nullable', 'string'],
            'input' => ['nullable', 'array'],
        ]));

        try {
            $runPublicId = $this->runner->start(
                processKey: $this->stringValue($validated['process_key'] ?? null),
                sourceType: $this->nullableString($validated['source_type'] ?? null) ?? 'manual',
                input: $this->inputArray($validated['input'] ?? null),
                actorPublicId: $this->actorPublicId($request),
                teamPublicId: $this->teamPublicId($request),
            );
        } catch (RuntimeException) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.run_failed'),
            ]);
        }

        return redirect()->route('admin.managed-processes.show', $runPublicId)->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.run_started'),
        ]);
    }

    public function runDefinition(Request $request, string $process): RedirectResponse
    {
        $definition = $this->definitions->get($process);

        if ($definition === null) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.definition_not_found'),
            ]);
        }

        try {
            $runPublicId = $this->runner->start(
                processKey: $definition->key,
                sourceType: 'manual',
                input: ['_input' => 'none'],
                actorPublicId: $this->actorPublicId($request),
                teamPublicId: $this->teamPublicId($request),
            );
        } catch (RuntimeException) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.run_failed'),
            ]);
        }

        return redirect()->route('admin.managed-processes.show', $runPublicId)->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.run_started'),
        ]);
    }

    public function retry(Request $request, string $run): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate(['reason' => ['required', 'string', 'max:1000']]));

        try {
            $newRun = $this->runner->retry($run, $this->actorPublicId($request), $this->teamPublicId($request), $this->stringValue($validated['reason'] ?? null));
        } catch (RuntimeException) {
            return redirect()->route('admin.managed-processes.show', $run)->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.retry_failed'),
            ]);
        }

        return redirect()->route('admin.managed-processes.show', $newRun)->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.retry_started'),
        ]);
    }

    public function cancel(Request $request, string $run): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate(['reason' => ['required', 'string', 'max:1000']]));

        try {
            $this->runner->cancel($run, $this->actorPublicId($request), $this->teamPublicId($request), $this->stringValue($validated['reason'] ?? null));
        } catch (RuntimeException) {
            return redirect()->route('admin.managed-processes.show', $run)->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.cancel_failed'),
            ]);
        }

        return redirect()->route('admin.managed-processes.show', $run)->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.cancelled'),
        ]);
    }

    public function schedules(): Response
    {
        return Inertia::render('Admin/ManagedProcesses/Schedules', [
            'definitions' => array_values(array_filter(
                array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all()),
                fn (array $definition): bool => ($definition['scheduleSupported'] ?? false) === true,
            )),
            'schedules' => $this->scheduleRows(),
            'summary' => [
                'schedules' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->where('enabled', true)->count(),
                'disabled' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->where('enabled', false)->count(),
            ],
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate([
            'process_key' => ['required', 'string'],
            'cron_expression' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:1000'],
        ]));
        $definition = $this->definitions->get($this->stringValue($validated['process_key'] ?? null));
        $cronExpression = trim($this->stringValue($validated['cron_expression'] ?? null));

        if ($definition === null || ! $definition->scheduleSupported) {
            return redirect()->route('admin.managed-processes.schedules.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.schedule_not_supported'),
            ]);
        }

        if (! $this->isValidCronExpression($cronExpression)) {
            throw ValidationException::withMessages([
                'cron_expression' => 'Enter a valid five-field cron expression.',
            ]);
        }

        DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->insert([
            'public_id' => (string) Str::ulid(),
            'process_key' => $definition->key,
            'module_key' => $definition->moduleKey,
            'scope' => $definition->scope,
            'team_id' => $this->teamId($request),
            'timezone' => 'Europe/Warsaw',
            'cron_expression' => $cronExpression,
            'interval_key' => null,
            'input_snapshot' => json_encode(['_input' => 'none'], JSON_THROW_ON_ERROR),
            'enabled' => true,
            'next_due_at' => $this->nextCronDueAt($cronExpression),
            'last_run_id' => null,
            'overlap_policy' => 'skip_if_active',
            'created_by_user_id' => $this->actorId($request),
            'updated_by_user_id' => $this->actorId($request),
            'reason' => $this->stringValue($validated['reason'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.managed-processes.schedules.index')->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.schedule_created'),
        ]);
    }

    public function disableSchedule(Request $request, string $schedule): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate(['reason' => ['nullable', 'string', 'max:1000']]));

        DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)
            ->where('public_id', $schedule)
            ->update([
                'enabled' => false,
                'updated_by_user_id' => $this->actorId($request),
                'reason' => $this->nullableString($validated['reason'] ?? null) ?? 'Disabled from managed process browser.',
                'updated_at' => now(),
            ]);

        return redirect()->route('admin.managed-processes.schedules.index')->with('flash.messages', [
            FlashMessage::success('flash.managed_processes.schedule_disabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definitionRow(ProcessDefinition $definition): array
    {
        return [
            'key' => $definition->key,
            'moduleKey' => $definition->moduleKey,
            'label' => $definition->label,
            'description' => $definition->description,
            'scope' => $definition->scope,
            'queueName' => $definition->queueName,
            'executionMode' => $definition->executionMode,
            'concurrencyPolicy' => $definition->concurrencyPolicy,
            'parallelism' => $definition->parallelism,
            'retryable' => $definition->retryPolicy->retryable,
            'cancellationPolicy' => $definition->cancellationPolicy,
            'scheduleSupported' => $definition->scheduleSupported,
            'manualStartSupported' => $definition->manualStartSupported,
            'externalEffects' => $definition->externalEffects,
            'highRisk' => $definition->highRisk,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runs(): array
    {
        return DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->leftJoin(DatabaseTable::USERS, 'process_runs.actor_user_id', '=', 'users.id')
            ->leftJoin(DatabaseTable::TEAMS, 'process_runs.team_id', '=', 'teams.id')
            ->orderByDesc('process_runs.created_at')
            ->limit(80)
            ->get(['process_runs.*', 'users.email as actor_email', 'teams.name as team_name'])
            ->map(fn (object $run): array => $this->runRow($run))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function runRow(object $run): array
    {
        return [
            'publicId' => $this->stringValue($run->public_id ?? null),
            'processKey' => $this->stringValue($run->process_key ?? null),
            'moduleKey' => $this->stringValue($run->module_key ?? null),
            'scope' => $this->stringValue($run->scope ?? null),
            'status' => $this->stringValue($run->status ?? null),
            'sourceType' => $this->stringValue($run->source_type ?? null),
            'stage' => $this->string($run->current_stage ?? null),
            'progressCurrent' => $this->intValue($run->progress_current ?? null),
            'progressTotal' => $this->nullableInt($run->progress_total ?? null),
            'progressLabel' => $this->string($run->progress_label ?? null),
            'counters' => $this->decode($run->counters ?? null),
            'inputSnapshot' => $this->decode($run->input_snapshot ?? null),
            'resultSummary' => $this->decode($run->result_summary ?? null),
            'safeErrorSummary' => $this->string($run->safe_error_summary ?? null),
            'queueName' => $this->string($run->queue_name ?? null),
            'correlationId' => $this->stringValue($run->correlation_id ?? null),
            'actor' => $this->string($run->actor_email ?? $run->actor_public_id ?? null),
            'team' => $this->string($run->team_name ?? $run->team_public_id ?? null),
            'createdAt' => $this->string($run->created_at ?? null),
            'queuedAt' => $this->string($run->queued_at ?? null),
            'startedAt' => $this->string($run->started_at ?? null),
            'finishedAt' => $this->string($run->finished_at ?? null),
            'canRetry' => in_array($this->stringValue($run->status ?? null), [ProcessRunStatus::Failed->value, ProcessRunStatus::SucceededWithWarnings->value, ProcessRunStatus::Cancelled->value], true),
            'canCancel' => in_array($this->stringValue($run->status ?? null), [ProcessRunStatus::Draft->value, ProcessRunStatus::Queued->value, ProcessRunStatus::Running->value, ProcessRunStatus::Waiting->value], true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function logs(int $runId): array
    {
        return DB::table(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS)
            ->where('process_run_id', $runId)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (object $log): array => [
                'publicId' => $this->stringValue($log->public_id ?? null),
                'occurredAt' => $this->stringValue($log->occurred_at ?? null),
                'severity' => $this->stringValue($log->severity ?? null),
                'eventType' => $this->stringValue($log->event_type ?? null),
                'stage' => $this->string($log->stage ?? null),
                'message' => $this->stringValue($log->message ?? null),
                'safeContext' => $this->decode($log->safe_context ?? null),
                'rowNumber' => $this->nullableInt($log->row_number ?? null),
                'entityPublicId' => $this->string($log->entity_public_id ?? null),
                'externalReference' => $this->string($log->external_reference ?? null),
                'sourceReference' => $this->string($log->source_reference ?? null),
                'errorCode' => $this->string($log->error_code ?? null),
                'exceptionClass' => $this->string($log->exception_class ?? null),
                'retryable' => ($log->retryable ?? null) === null ? null : (bool) $log->retryable,
                'correlationId' => $this->stringValue($log->correlation_id ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function importExecution(int $runId): ?array
    {
        $execution = DB::table(DatabaseTable::IMPORT_EXECUTIONS)->where('process_run_id', $runId)->first();

        if (! is_object($execution)) {
            return null;
        }

        return [
            'publicId' => $this->stringValue($execution->public_id ?? null),
            'importKey' => $this->stringValue($execution->import_key ?? null),
            'sourceType' => $this->stringValue($execution->source_type ?? null),
            'apiReference' => $this->string($execution->api_reference ?? null),
            'externalReference' => $this->string($execution->external_reference ?? null),
            'mappingSnapshot' => $this->decode($execution->mapping_snapshot ?? null),
            'sourceMetadata' => $this->decode($execution->source_metadata ?? null),
            'statistics' => $this->decode($execution->statistics ?? null),
            'idempotencyKey' => $this->string($execution->idempotency_key ?? null),
            'idempotencyState' => $this->stringValue($execution->idempotency_state ?? null),
            'errors' => DB::table(DatabaseTable::IMPORT_ROW_ERRORS)
                ->where('import_execution_id', $this->intValue($execution->id ?? null))
                ->orderBy('row_number')
                ->get()
                ->map(fn (object $error): array => [
                    'publicId' => $this->stringValue($error->public_id ?? null),
                    'rowNumber' => $this->nullableInt($error->row_number ?? null),
                    'fieldName' => $this->string($error->field_name ?? null),
                    'severity' => $this->stringValue($error->severity ?? null),
                    'errorCode' => $this->stringValue($error->error_code ?? null),
                    'message' => $this->stringValue($error->message ?? null),
                    'safeContext' => $this->decode($error->safe_context ?? null),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRows(): array
    {
        return DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)
            ->leftJoin(DatabaseTable::TEAMS, 'process_schedules.team_id', '=', 'teams.id')
            ->orderByDesc('process_schedules.created_at')
            ->get(['process_schedules.*', 'teams.name as team_name'])
            ->map(fn (object $schedule): array => [
                'publicId' => $this->stringValue($schedule->public_id ?? null),
                'processKey' => $this->stringValue($schedule->process_key ?? null),
                'moduleKey' => $this->stringValue($schedule->module_key ?? null),
                'scope' => $this->stringValue($schedule->scope ?? null),
                'team' => $this->string($schedule->team_name ?? null),
                'timezone' => $this->stringValue($schedule->timezone ?? null),
                'cronExpression' => $this->string($schedule->cron_expression ?? null),
                'intervalKey' => $this->string($schedule->interval_key ?? null),
                'enabled' => (bool) $schedule->enabled,
                'nextDueAt' => $this->string($schedule->next_due_at ?? null),
                'overlapPolicy' => $this->stringValue($schedule->overlap_policy ?? null),
                'reason' => $this->stringValue($schedule->reason ?? null),
                'createdAt' => $this->string($schedule->created_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function importExecutions(): array
    {
        return DB::table(DatabaseTable::IMPORT_EXECUTIONS)
            ->join(DatabaseTable::MANAGED_PROCESS_RUNS, 'import_executions.process_run_id', '=', 'process_runs.id')
            ->orderByDesc('import_executions.created_at')
            ->limit(80)
            ->get([
                'import_executions.public_id',
                'import_executions.import_key',
                'import_executions.source_type',
                'import_executions.statistics',
                'import_executions.idempotency_key',
                'import_executions.idempotency_state',
                'import_executions.created_at',
                'process_runs.public_id as run_public_id',
                'process_runs.status',
            ])
            ->map(fn (object $row): array => [
                'publicId' => $this->stringValue($row->public_id ?? null),
                'runPublicId' => $this->stringValue($row->run_public_id ?? null),
                'importKey' => $this->stringValue($row->import_key ?? null),
                'sourceType' => $this->stringValue($row->source_type ?? null),
                'status' => $this->stringValue($row->status ?? null),
                'statistics' => $this->compactJson($this->decode($row->statistics ?? null)),
                'idempotencyKey' => $this->nullableString($row->idempotency_key ?? null),
                'idempotencyState' => $this->stringValue($row->idempotency_state ?? null),
                'createdAt' => $this->nullableString($row->created_at ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'active' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->whereIn('status', ['draft', 'queued', 'running', 'waiting'])->count(),
            'failed24h' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'warnings24h' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->where('status', 'succeeded_with_warnings')->where('created_at', '>=', now()->subDay())->count(),
            'imports' => (int) DB::table(DatabaseTable::IMPORT_EXECUTIONS)->count(),
        ];
    }

    private function actorPublicId(Request $request): ?string
    {
        $value = data_get($request->user(), 'public_id');

        return is_string($value) ? $value : null;
    }

    private function actorId(Request $request): ?int
    {
        $value = data_get($request->user(), 'id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function teamPublicId(Request $request): ?string
    {
        $value = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($value) ? $value : null;
    }

    private function teamId(Request $request): ?int
    {
        $publicId = $this->teamPublicId($request);

        if ($publicId === null) {
            return null;
        }

        $id = DB::table(DatabaseTable::TEAMS)->where('public_id', $publicId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function isValidCronExpression(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || count($parts) !== 5) {
            return false;
        }

        return $this->validCronField($parts[0], 0, 59)
            && $this->validCronField($parts[1], 0, 23)
            && $this->validCronField($parts[2], 1, 31)
            && $this->validCronField($parts[3], 1, 12)
            && $this->validCronField($parts[4], 0, 7);
    }

    private function validCronField(string $field, int $min, int $max): bool
    {
        foreach (explode(',', $field) as $part) {
            if ($part === '') {
                return false;
            }

            [$range, $step] = $this->cronRangeAndStep($part);

            if ($step !== null && (! ctype_digit($step) || (int) $step < 1)) {
                return false;
            }

            if ($range === '*') {
                continue;
            }

            if (str_contains($range, '-')) {
                [$start, $end] = $this->cronRangeBounds($range);

                if (! $this->validCronNumber($start, $min, $max) || ! $this->validCronNumber($end, $min, $max) || (int) $start > (int) $end) {
                    return false;
                }

                continue;
            }

            if (! $this->validCronNumber($range, $min, $max)) {
                return false;
            }
        }

        return true;
    }

    private function validCronNumber(string $value, int $min, int $max): bool
    {
        return ctype_digit($value) && (int) $value >= $min && (int) $value <= $max;
    }

    private function nextCronDueAt(string $expression): Carbon
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || count($parts) !== 5) {
            return now()->addMinute();
        }

        $candidate = now('Europe/Warsaw')->addMinute()->startOfMinute();
        $limit = $candidate->copy()->addYear();

        while ($candidate->lessThan($limit)) {
            $dayOfMonthMatches = $this->cronFieldMatches($parts[2], (int) $candidate->format('j'), 1, 31);
            $dayOfWeekMatches = $this->cronFieldMatches($parts[4], (int) $candidate->format('w'), 0, 7);
            $dayMatches = $parts[2] !== '*' && $parts[4] !== '*'
                ? ($dayOfMonthMatches || $dayOfWeekMatches)
                : ($dayOfMonthMatches && $dayOfWeekMatches);

            if (
                $this->cronFieldMatches($parts[0], (int) $candidate->format('i'), 0, 59)
                && $this->cronFieldMatches($parts[1], (int) $candidate->format('G'), 0, 23)
                && $this->cronFieldMatches($parts[3], (int) $candidate->format('n'), 1, 12)
                && $dayMatches
            ) {
                return $candidate;
            }

            $candidate->addMinute();
        }

        return now('Europe/Warsaw')->addYear();
    }

    private function cronFieldMatches(string $field, int $value, int $min, int $max): bool
    {
        $normalizedValue = $max === 7 && $value === 0 ? [0, 7] : [$value];

        foreach (explode(',', $field) as $part) {
            [$range, $step] = $this->cronRangeAndStep($part);
            $stepValue = $step === null ? 1 : (int) $step;
            $start = $min;
            $end = $max;

            if ($range !== '*') {
                if (str_contains($range, '-')) {
                    [$startRaw, $endRaw] = $this->cronRangeBounds($range);
                    $start = (int) $startRaw;
                    $end = (int) $endRaw;
                } else {
                    $start = (int) $range;
                    $end = (int) $range;
                }
            }

            foreach ($normalizedValue as $candidate) {
                if ($candidate >= $start && $candidate <= $end && ($candidate - $start) % $stepValue === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function cronRangeAndStep(string $part): array
    {
        $pieces = explode('/', $part, 2);

        return [$pieces[0], $pieces[1] ?? null];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cronRangeBounds(string $range): array
    {
        $pieces = explode('-', $range, 2);

        return [$pieces[0], $pieces[1] ?? ''];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function compactJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyedArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inputArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return $this->stringKeyedArray($value);
    }
}
