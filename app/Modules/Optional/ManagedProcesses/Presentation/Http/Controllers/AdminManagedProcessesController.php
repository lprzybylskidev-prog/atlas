<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunner;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        private AuditRecorder $audit,
        private ProcessDefinitionRegistry $definitions,
        private ManagedProcessRunner $runner,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::MANAGED_PROCESS_RUNS);
        $filters = $this->runFilters($request);
        $result = $this->tableResult($request, $definition, $this->filteredRuns($this->runs(), $filters));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/ManagedProcesses/Runs', [
            'runs' => $result->rows,
            'summary' => $this->summary(),
            'filterOptions' => $this->runFilterOptions(),
            'table' => $table,
        ]);
    }

    public function definitions(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::MANAGED_PROCESS_DEFINITIONS);
        $filters = $this->definitionFilters($request);
        $definitionRows = array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all());
        $result = $this->tableResult($request, $definition, $this->filteredDefinitions($definitionRows, $filters));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/ManagedProcesses/Definitions', [
            'definitions' => $result->rows,
            'summary' => [
                'definitions' => count($this->definitions->all()),
                'schedulable' => count(array_filter($this->definitions->all(), fn (ProcessDefinition $definition): bool => $definition->scheduleSupported)),
                'manual' => count(array_filter($this->definitions->all(), fn (ProcessDefinition $definition): bool => $definition->manualStartSupported)),
            ],
            'filterOptions' => $this->definitionFilterOptions($definitionRows),
            'table' => $table,
        ]);
    }

    public function show(Request $request, string $run): Response
    {
        $record = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS.' as process_runs')
            ->leftJoin(DatabaseTable::USERS, 'process_runs.actor_user_id', '=', 'users.id')
            ->leftJoin(DatabaseTable::TEAMS, 'process_runs.team_id', '=', 'teams.id')
            ->leftJoin(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->leftJoin(DatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'acknowledgements.acknowledged_by_user_id')
            ->where('process_runs.public_id', $run)
            ->first([
                'process_runs.*',
                'users.public_id as actor_public_id',
                'users.email as actor_email',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'acknowledgements.acknowledged_at',
                'acknowledged_users.email as acknowledged_by',
            ]);

        abort_if(! is_object($record), 404);

        return Inertia::render('Admin/ManagedProcesses/Show', [
            'run' => $this->runRow($record),
            'logs' => $this->logs($request, $this->intValue($record->id ?? null)),
            'importExecution' => $this->importExecution($this->intValue($record->id ?? null)),
            'filterOptions' => $this->logFilterOptions($this->intValue($record->id ?? null)),
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

    public function startDefinition(Request $request, string $process, FileStorage $files): RedirectResponse
    {
        $definition = $this->definitions->get($process);

        if ($definition === null) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.definition_not_found'),
            ]);
        }

        $validated = $this->stringKeyedArray($request->validate([
            'upload_file' => ['nullable', 'file'],
            'watched_directory' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]));

        $input = ['_input' => 'none'];
        $sourceType = 'manual';

        if ($this->definitionSupportsFileInput($definition)) {
            $input = [
                'source_type' => $request->hasFile('upload_file') ? 'csv' : 'watched_directory',
                'idempotency_key' => $this->nullableString($validated['idempotency_key'] ?? null) ?? (string) Str::ulid(),
            ];
            $sourceType = 'file_import';

            if ($request->hasFile('upload_file')) {
                $upload = $request->file('upload_file');

                if (! $upload instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'upload_file' => __('validation.file'),
                    ]);
                }

                $stored = $files->storeUpload($upload, $this->actorId($request), $this->teamId($request), [
                    'source' => 'managed_process_manual_upload',
                    'process_key' => $definition->key,
                ]);
                $input['file_public_id'] = $stored->publicId;
            }

            $watchedDirectory = trim($this->nullableString($validated['watched_directory'] ?? null) ?? '');

            if ($watchedDirectory !== '') {
                $input['watched_directory'] = $watchedDirectory;
            }

            if (! isset($input['file_public_id']) && ! isset($input['watched_directory'])) {
                throw ValidationException::withMessages([
                    'upload_file' => __('validation.required'),
                ]);
            }
        }

        try {
            $runPublicId = $this->runner->start(
                processKey: $definition->key,
                sourceType: $sourceType,
                input: $input,
                actorPublicId: $this->actorPublicId($request),
                teamPublicId: $this->teamPublicId($request),
            );
        } catch (RuntimeException) {
            return redirect()->route('admin.managed-processes.definitions.index')->with('flash.messages', [
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

    public function acknowledge(Request $request): RedirectResponse
    {
        $validated = $this->validatedAcknowledge($request);
        $runs = DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->whereIn('public_id', $validated['runs'])
            ->orderByDesc('created_at')
            ->get(['id', 'public_id', 'process_key', 'module_key', 'status'])
            ->all();

        if (count($runs) !== count($validated['runs'])) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.acknowledge_missing'),
            ]);
        }

        $acknowledgeableRuns = array_values(array_filter($runs, fn (object $run): bool => $this->requiresAttention($this->stringValue($run->status ?? null))));

        if ($acknowledgeableRuns === []) {
            return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
                FlashMessage::error('flash.managed_processes.acknowledge_unavailable'),
            ]);
        }

        $this->acknowledgeRuns($request, $acknowledgeableRuns, $validated['reason']);
        $this->recordAcknowledgeAudit($request, $acknowledgeableRuns, $validated['reason']);

        return redirect()->route('admin.managed-processes.index')->with('flash.messages', [
            FlashMessage::success(count($acknowledgeableRuns) === 1 ? 'flash.managed_processes.acknowledge_single' : 'flash.managed_processes.acknowledge_multiple'),
        ]);
    }

    public function schedules(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::MANAGED_PROCESS_SCHEDULES);
        $filters = $this->scheduleFilters($request);
        $result = $this->tableResult($request, $definition, $this->filteredSchedules($this->scheduleRows(), $filters));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/ManagedProcesses/Schedules', [
            'definitions' => array_values(array_filter(
                array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all()),
                fn (array $definition): bool => ($definition['scheduleSupported'] ?? false) === true,
            )),
            'schedules' => $result->rows,
            'summary' => [
                'schedules' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->where('enabled', true)->count(),
                'disabled' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)->where('enabled', false)->count(),
            ],
            'filterOptions' => $this->scheduleFilterOptions(),
            'table' => $table,
        ]);
    }

    public function createSchedule(): Response
    {
        return Inertia::render('Admin/ManagedProcesses/Schedules/Create', [
            'definitions' => array_values(array_filter(
                array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all()),
                fn (array $definition): bool => ($definition['scheduleSupported'] ?? false) === true,
            )),
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $this->stringKeyedArray($request->validate([
            'process_key' => ['required', 'string'],
            'cron_expression' => ['required', 'string', 'max:120'],
            'watched_directory' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
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
                'cron_expression' => __('validation.managed_processes.cron_expression'),
            ]);
        }

        $inputSnapshot = ['_input' => 'none'];

        if ($this->definitionSupportsWatchedDirectory($definition)) {
            $watchedDirectory = trim($this->nullableString($validated['watched_directory'] ?? null) ?? '');

            if ($watchedDirectory === '') {
                throw ValidationException::withMessages([
                    'watched_directory' => __('validation.required'),
                ]);
            }

            $inputSnapshot = [
                'source_type' => 'watched_directory',
                'watched_directory' => $watchedDirectory,
                'idempotency_key' => $this->nullableString($validated['idempotency_key'] ?? null) ?? (string) Str::ulid(),
            ];
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
            'input_snapshot' => json_encode($inputSnapshot, JSON_THROW_ON_ERROR),
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
     * @return array{process: string, status: string, source: string, module: string, import: string, idempotency: string, handling: string, from: string, to: string}
     */
    private function runFilters(Request $request): array
    {
        return [
            'process' => $this->oneOf($request->query('process'), $this->allOr($this->distinctProcessRunValues('process_key'))),
            'status' => $this->oneOf($request->query('status'), $this->allOr(array_map(static fn (ProcessRunStatus $status): string => $status->value, ProcessRunStatus::cases()))),
            'source' => $this->oneOf($request->query('source'), $this->allOr($this->distinctProcessRunValues('source_type'))),
            'module' => $this->oneOf($request->query('module'), $this->allOr($this->distinctProcessRunValues('module_key'))),
            'import' => $this->oneOf($request->query('import'), $this->allOr($this->distinctImportValues('import_key'))),
            'idempotency' => $this->oneOf($request->query('idempotency'), $this->allOr($this->distinctImportValues('idempotency_state'))),
            'handling' => $this->oneOf($request->query('handling', 'all'), ['all', 'needs_attention', 'handled', 'ok']),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];
    }

    /**
     * @return array{processes: list<string>, statuses: list<string>, sources: list<string>, modules: list<string>, imports: list<string>, idempotencyStates: list<string>}
     */
    private function runFilterOptions(): array
    {
        return [
            'processes' => $this->distinctProcessRunValues('process_key'),
            'statuses' => array_map(static fn (ProcessRunStatus $status): string => $status->value, ProcessRunStatus::cases()),
            'sources' => $this->distinctProcessRunValues('source_type'),
            'modules' => $this->distinctProcessRunValues('module_key'),
            'imports' => $this->distinctImportValues('import_key'),
            'idempotencyStates' => $this->distinctImportValues('idempotency_state'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{process: string, status: string, source: string, module: string, import: string, idempotency: string, handling: string, from: string, to: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRuns(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            foreach (['processKey' => 'process', 'status' => 'status', 'sourceType' => 'source', 'moduleKey' => 'module', 'importKey' => 'import', 'idempotencyState' => 'idempotency'] as $column => $filter) {
                if ($filters[$filter] !== 'all' && $row[$column] !== $filters[$filter]) {
                    return false;
                }
            }

            if ($filters['handling'] !== 'all' && ($row['handlingStatus'] ?? null) !== $filters['handling']) {
                return false;
            }

            return self::dateRangeMatches(self::stringField($row, 'startedAt') ?: self::stringField($row, 'createdAt'), $filters['from'], $filters['to']);
        }));
    }

    /**
     * @return array{module: string, queue: string, manual: string, schedule: string, risk: string}
     */
    private function definitionFilters(Request $request): array
    {
        $rows = array_map(fn (ProcessDefinition $definition): array => $this->definitionRow($definition), $this->definitions->all());

        return [
            'module' => $this->oneOf($request->query('module'), $this->allOr($this->uniqueValues($rows, 'moduleKey'))),
            'queue' => $this->oneOf($request->query('queue'), $this->allOr($this->uniqueValues($rows, 'queueName'))),
            'manual' => $this->oneOf($request->query('manual'), ['all', 'yes', 'no']),
            'schedule' => $this->oneOf($request->query('schedule'), ['all', 'yes', 'no']),
            'risk' => $this->oneOf($request->query('risk'), ['all', 'high', 'external', 'standard']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{modules: list<string>, queues: list<string>}
     */
    private function definitionFilterOptions(array $rows): array
    {
        return [
            'modules' => $this->uniqueValues($rows, 'moduleKey'),
            'queues' => $this->uniqueValues($rows, 'queueName'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{module: string, queue: string, manual: string, schedule: string, risk: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredDefinitions(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['module'] !== 'all' && $row['moduleKey'] !== $filters['module']) {
                return false;
            }

            if ($filters['queue'] !== 'all' && $row['queueName'] !== $filters['queue']) {
                return false;
            }

            if ($filters['manual'] !== 'all' && $row['manualStartSupported'] !== ($filters['manual'] === 'yes')) {
                return false;
            }

            if ($filters['schedule'] !== 'all' && $row['scheduleSupported'] !== ($filters['schedule'] === 'yes')) {
                return false;
            }

            if ($filters['risk'] === 'high' && $row['highRisk'] !== true) {
                return false;
            }

            if ($filters['risk'] === 'external' && $row['externalEffects'] !== true) {
                return false;
            }

            if ($filters['risk'] === 'standard' && ($row['highRisk'] === true || $row['externalEffects'] === true)) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array{process: string, enabled: string, module: string, from: string, to: string}
     */
    private function scheduleFilters(Request $request): array
    {
        return [
            'process' => $this->oneOf($request->query('process'), $this->allOr($this->distinctScheduleValues('process_key'))),
            'enabled' => $this->oneOf($request->query('enabled'), ['all', 'yes', 'no']),
            'module' => $this->oneOf($request->query('module'), $this->allOr($this->distinctScheduleValues('module_key'))),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];
    }

    /**
     * @return array{processes: list<string>, modules: list<string>}
     */
    private function scheduleFilterOptions(): array
    {
        return [
            'processes' => $this->distinctScheduleValues('process_key'),
            'modules' => $this->distinctScheduleValues('module_key'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{process: string, enabled: string, module: string, from: string, to: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredSchedules(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['process'] !== 'all' && $row['processKey'] !== $filters['process']) {
                return false;
            }

            if ($filters['enabled'] !== 'all' && $row['enabled'] !== ($filters['enabled'] === 'yes')) {
                return false;
            }

            if ($filters['module'] !== 'all' && $row['moduleKey'] !== $filters['module']) {
                return false;
            }

            return self::dateRangeMatches(self::stringField($row, 'nextDueAt') ?: self::stringField($row, 'createdAt'), $filters['from'], $filters['to']);
        }));
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
            'supportsFileUpload' => $this->definitionSupportsFileInput($definition),
            'supportsWatchedDirectory' => $this->definitionSupportsWatchedDirectory($definition),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runs(): array
    {
        return array_values(DB::table(DatabaseTable::MANAGED_PROCESS_RUNS.' as process_runs')
            ->leftJoin(DatabaseTable::USERS, 'process_runs.actor_user_id', '=', 'users.id')
            ->leftJoin(DatabaseTable::TEAMS, 'process_runs.team_id', '=', 'teams.id')
            ->leftJoin(DatabaseTable::IMPORT_EXECUTIONS, 'import_executions.process_run_id', '=', 'process_runs.id')
            ->leftJoin(DatabaseTable::FILE_OBJECTS, 'import_executions.file_object_id', '=', 'file_objects.id')
            ->leftJoin(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->leftJoin(DatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'acknowledgements.acknowledged_by_user_id')
            ->orderByDesc('process_runs.created_at')
            ->limit(80)
            ->get([
                'process_runs.*',
                'users.email as actor_email',
                'teams.name as team_name',
                'acknowledgements.acknowledged_at',
                'acknowledged_users.email as acknowledged_by',
                'import_executions.import_key',
                'import_executions.source_type as import_source_type',
                'import_executions.idempotency_key',
                'import_executions.idempotency_state',
                'file_objects.original_name as import_file',
            ])
            ->map(fn (object $run): array => $this->runRow($run))
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function runRow(object $run): array
    {
        $status = $this->stringValue($run->status ?? null);
        $acknowledgedAt = $this->nullableString($run->acknowledged_at ?? null);
        $needsAttention = $this->requiresAttention($status);

        return [
            'publicId' => $this->stringValue($run->public_id ?? null),
            'processKey' => $this->stringValue($run->process_key ?? null),
            'moduleKey' => $this->stringValue($run->module_key ?? null),
            'importKey' => $this->nullableString($run->import_key ?? null),
            'importSourceType' => $this->nullableString($run->import_source_type ?? null),
            'importFile' => $this->nullableString($run->import_file ?? null),
            'idempotencyKey' => $this->nullableString($run->idempotency_key ?? null),
            'idempotencyState' => $this->nullableString($run->idempotency_state ?? null),
            'scope' => $this->stringValue($run->scope ?? null),
            'status' => $status,
            'acknowledged' => $acknowledgedAt !== null,
            'handlingStatus' => $acknowledgedAt !== null ? 'handled' : ($needsAttention ? 'needs_attention' : 'ok'),
            'acknowledgedAt' => $acknowledgedAt,
            'acknowledgedBy' => $this->nullableString($run->acknowledged_by ?? null),
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
            'canRetry' => in_array($status, [ProcessRunStatus::Failed->value, ProcessRunStatus::SucceededWithWarnings->value, ProcessRunStatus::Cancelled->value], true),
            'canCancel' => in_array($status, [ProcessRunStatus::Draft->value, ProcessRunStatus::Queued->value, ProcessRunStatus::Running->value, ProcessRunStatus::Waiting->value], true),
            'canAcknowledge' => $needsAttention && $acknowledgedAt === null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function logs(Request $request, int $runId): array
    {
        $filters = [
            'severity' => $this->oneOf($request->query('severity'), $this->allOr($this->distinctLogValues($runId, 'severity'))),
            'event' => $this->oneOf($request->query('event'), $this->allOr($this->distinctLogValues($runId, 'event_type'))),
            'stage' => $this->oneOf($request->query('stage'), $this->allOr($this->distinctLogValues($runId, 'stage'))),
        ];

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
            ->filter(static function (array $row) use ($filters): bool {
                foreach (['severity' => 'severity', 'eventType' => 'event', 'stage' => 'stage'] as $column => $filter) {
                    if ($filters[$filter] !== 'all' && $row[$column] !== $filters[$filter]) {
                        return false;
                    }
                }

                return true;
            })
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
     * @return list<array<string, mixed>>
     */
    private function scheduleRows(): array
    {
        return array_values(DB::table(DatabaseTable::MANAGED_PROCESS_SCHEDULES)
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
            ->all());
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'active' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_RUNS)->whereIn('status', ['draft', 'queued', 'running', 'waiting'])->count(),
            'failed24h' => (int) $this->unacknowledgedAttentionRunsQuery()->where('process_runs.status', 'failed')->where('process_runs.created_at', '>=', now()->subDay())->count(),
            'warnings24h' => (int) $this->unacknowledgedAttentionRunsQuery()->where('process_runs.status', 'succeeded_with_warnings')->where('process_runs.created_at', '>=', now()->subDay())->count(),
            'handled' => (int) DB::table(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS)->count(),
            'imports' => (int) DB::table(DatabaseTable::IMPORT_EXECUTIONS)->count(),
        ];
    }

    private function unacknowledgedAttentionRunsQuery(): Builder
    {
        return DB::table(DatabaseTable::MANAGED_PROCESS_RUNS.' as process_runs')
            ->leftJoin(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->whereNull('acknowledgements.process_run_id');
    }

    /**
     * @return array{severities: list<string>, eventTypes: list<string>, stages: list<string>}
     */
    private function logFilterOptions(int $runId): array
    {
        return [
            'severities' => $this->distinctLogValues($runId, 'severity'),
            'eventTypes' => $this->distinctLogValues($runId, 'event_type'),
            'stages' => $this->distinctLogValues($runId, 'stage'),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return array_values(array_unique(array_merge(['all'], $values)));
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

        return $to === '' || $date <= $to;
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
    private function distinctProcessRunValues(string $column): array
    {
        return $this->distinctValues(DatabaseTable::MANAGED_PROCESS_RUNS, $column);
    }

    /**
     * @return list<string>
     */
    private function distinctImportValues(string $column): array
    {
        return $this->distinctValues(DatabaseTable::IMPORT_EXECUTIONS, $column);
    }

    /**
     * @return list<string>
     */
    private function distinctScheduleValues(string $column): array
    {
        return $this->distinctValues(DatabaseTable::MANAGED_PROCESS_SCHEDULES, $column);
    }

    /**
     * @return list<string>
     */
    private function distinctLogValues(int $runId, string $column): array
    {
        return array_values(DB::table(DatabaseTable::MANAGED_PROCESS_LOG_EVENTS)
            ->where('process_run_id', $runId)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all());
    }

    /**
     * @return array{runs: list<string>, reason: ?string}
     */
    private function validatedAcknowledge(Request $request): array
    {
        $validated = $request->validate([
            'runs' => ['required', 'array', 'min:1', 'max:100'],
            'runs.*' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $values = is_array($validated) ? $validated : [];
        $rawRuns = $values['runs'] ?? [];
        $runs = [];

        if (is_array($rawRuns)) {
            foreach ($rawRuns as $run) {
                if (is_string($run) && $run !== '' && ! in_array($run, $runs, true)) {
                    $runs[] = $run;
                }
            }
        }

        return [
            'runs' => $runs,
            'reason' => is_string($values['reason'] ?? null) && trim($values['reason']) !== '' ? trim($values['reason']) : null,
        ];
    }

    /**
     * @param  array<int, object>  $runs
     */
    private function acknowledgeRuns(Request $request, array $runs, ?string $reason): void
    {
        $actorId = $this->actorId($request);
        $now = now();

        foreach ($runs as $run) {
            $runId = $this->intValue($run->id ?? null);
            $existing = DB::table(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS)->where('process_run_id', $runId)->exists();
            $values = [
                'acknowledged_by_user_id' => $actorId,
                'reason' => $reason,
                'acknowledged_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS)->where('process_run_id', $runId)->update($values);

                continue;
            }

            DB::table(DatabaseTable::MANAGED_PROCESS_RUN_ACKNOWLEDGEMENTS)->insert($values + [
                'public_id' => (string) Str::ulid(),
                'process_run_id' => $runId,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, object>  $runs
     */
    private function recordAcknowledgeAudit(Request $request, array $runs, ?string $reason): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $correlationId = $request->attributes->get('correlation_id');
        $publicIds = [];
        $processKeys = [];
        $moduleKeys = [];

        foreach ($runs as $run) {
            $publicIds[] = $this->stringValue($run->public_id ?? null);
            $processKeys[] = $this->stringValue($run->process_key ?? null);
            $moduleKeys[] = $this->stringValue($run->module_key ?? null);
        }

        $this->audit->record(new AuditEvent(
            module: 'managed_processes',
            action: count($runs) === 1 ? 'managed_process.run_acknowledge' : 'managed_process.runs_acknowledge',
            result: 'succeeded',
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: count($runs) === 1 ? 'managed_process_run' : 'managed_process_runs',
            targetPublicId: count($runs) === 1 ? $publicIds[0] : null,
            aggregateType: 'managed_process',
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            correlationId: is_string($correlationId) ? $correlationId : null,
            reason: $reason,
            metadata: [
                'count' => count($runs),
                'run_public_ids' => array_slice($publicIds, 0, 100),
                'process_keys' => array_values(array_unique($processKeys)),
                'module_keys' => array_values(array_unique($moduleKeys)),
            ],
        ));
    }

    private function requiresAttention(string $status): bool
    {
        return in_array($status, [
            ProcessRunStatus::Failed->value,
            ProcessRunStatus::SucceededWithWarnings->value,
            ProcessRunStatus::Cancelled->value,
            ProcessRunStatus::Expired->value,
        ], true);
    }

    /**
     * @return list<string>
     */
    private function distinctValues(string $table, string $column): array
    {
        return array_values(DB::table($table)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all());
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

    private function definitionSupportsFileInput(ProcessDefinition $definition): bool
    {
        $properties = $definition->inputSchema['properties'] ?? null;

        return is_array($properties) && array_key_exists('file_public_id', $properties);
    }

    private function definitionSupportsWatchedDirectory(ProcessDefinition $definition): bool
    {
        $properties = $definition->inputSchema['properties'] ?? null;

        return is_array($properties) && array_key_exists('watched_directory', $properties);
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
