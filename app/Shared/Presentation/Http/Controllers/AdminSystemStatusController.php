<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Modules\Core\Files\Application\Public\Persistence\FilesDatabaseTable;
use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\Imports\Application\Public\Persistence\ImportsDatabaseTable;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Modules\Optional\ManagedProcesses\Application\Public\Persistence\ManagedProcessesDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Observability\ModuleActivationScheduleDiagnostics;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminSystemStatusController
{
    public function __construct(
        private ModuleGate $moduleGate,
        private ModuleRegistry $moduleRegistry,
        private ModuleActivationService $moduleActivation,
        private ReadinessChecker $readiness,
        private SchedulerHeartbeatMonitor $schedulerHeartbeat,
        private ModuleActivationScheduleDiagnostics $moduleActivationDiagnostics,
    ) {}

    public function __invoke(Request $request): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userPublicId = is_string($userPublicId) ? $userPublicId : null;
        $teamPublicId = is_string($teamPublicId) ? $teamPublicId : null;

        return Inertia::render('Admin/SystemStatus', [
            'dashboard' => $this->dashboardPayload($request),
            'availability' => [
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.release',
                    request: new ModuleAccessRequest(
                        moduleKey: 'health',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.release',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.readiness',
                    request: new ModuleAccessRequest(
                        moduleKey: 'health',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.readiness',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.modules',
                    request: new ModuleAccessRequest(
                        moduleKey: 'authorization',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status.modules',
                    ),
                ),
            ],
        ]);
    }

    public function release(): JsonResponse
    {
        $deployedAt = config('atlas.release.deployed_at');
        $deployedBy = config('atlas.release.deployed_by');
        $source = config('atlas.release.source');

        return response()->json([
            'data' => [
                'label' => 'Release',
                'value' => config()->string('atlas.release.version'),
                'description' => 'Application release identity and last deployment metadata.',
                'status' => 'healthy',
                'releaseVersion' => config()->string('atlas.release.version'),
                'releaseId' => config()->string('atlas.release.id'),
                'environment' => config()->string('app.env'),
                'laravelVersion' => Application::VERSION,
                'phpVersion' => PHP_VERSION,
                'timezone' => config()->string('app.timezone'),
                'runtime' => PHP_SAPI,
                'deployedAt' => is_scalar($deployedAt) ? (string) $deployedAt : null,
                'deployedBy' => is_scalar($deployedBy) ? (string) $deployedBy : null,
                'deploySource' => is_scalar($source) ? (string) $source : null,
            ],
            'empty' => false,
        ]);
    }

    public function readiness(): JsonResponse
    {
        $report = $this->readiness->check();
        $payload = $report->toAdminArray();

        return response()->json([
            'data' => [
                'label' => 'Readiness',
                'value' => ucfirst($payload['status']),
                'description' => sprintf(
                    '%d blocking failure(s), %d degraded dependency issue(s).',
                    $payload['blocking']['failed'],
                    $payload['degraded']['failed'],
                ),
                'status' => $payload['status'],
                'checkedAt' => $payload['checked_at'],
                'blockingFailed' => $payload['blocking']['failed'],
                'blockingTotal' => $payload['blocking']['total'],
                'degradedFailed' => $payload['degraded']['failed'],
                'degradedTotal' => $payload['degraded']['total'],
                'checks' => $payload['checks'],
            ],
            'empty' => false,
        ]);
    }

    public function modules(Request $request): JsonResponse
    {
        $teamId = $this->activeTeamId($request);
        $modules = array_map(fn (ModuleDefinition $module): array => $this->moduleStatusRow($module, $teamId), $this->moduleRegistry->all());
        $activeCount = count(array_filter($modules, static fn (array $module): bool => $module['effectiveEnabled'] === true));
        $attentionCount = count(array_filter(
            $modules,
            static fn (array $module): bool => in_array($module['status'], ['degraded', 'unhealthy', 'unavailable'], true),
        ));

        return response()->json([
            'data' => [
                'label' => 'Modules',
                'value' => sprintf('%d active / %d modules', $activeCount, count($modules)),
                'description' => $attentionCount > 0
                    ? sprintf('%d module(s) need operator attention.', $attentionCount)
                    : 'All active modules are healthy.',
                'status' => $attentionCount > 0 ? 'degraded' : 'healthy',
                'activeCount' => $activeCount,
                'moduleCount' => count($modules),
                'attentionCount' => $attentionCount,
                'modules' => $modules,
            ],
            'empty' => false,
        ]);
    }

    public function scheduler(): JsonResponse
    {
        $status = $this->schedulerHeartbeat->status();

        return response()->json([
            'data' => [
                'label' => 'Scheduler',
                'value' => $status['label'],
                'description' => $status['description'],
                'status' => $status['status'],
                'lastSuccessAt' => $status['lastSuccessAt'],
                'lastStartedAt' => $status['lastStartedAt'],
                'lastFinishedAt' => $status['lastFinishedAt'],
                'lastRuntimeMs' => $status['lastRuntimeMs'],
                'lastError' => $status['lastError'],
                'staleAfterSeconds' => $status['staleAfterSeconds'],
            ],
            'empty' => false,
        ]);
    }

    public function failedJobs(): JsonResponse
    {
        $aggregate = DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->whereNull('acknowledgements.failed_job_uuid')
            ->selectRaw('count(*) as failed_count')
            ->selectRaw('count(distinct failed_jobs.queue) as queues')
            ->selectRaw('max(failed_jobs.failed_at) as latest_failed_at')
            ->first();
        $failedCount = is_object($aggregate) && is_numeric($aggregate->failed_count ?? null) ? (int) $aggregate->failed_count : 0;

        return response()->json([
            'data' => [
                'label' => 'Failed jobs',
                'value' => (string) $failedCount,
                'description' => $failedCount > 0
                    ? 'Failed jobs are waiting for operator review.'
                    : 'No failed jobs are recorded.',
                'status' => $failedCount > 0 ? 'degraded' : 'healthy',
                'failedCount' => $failedCount,
                'queueCount' => is_object($aggregate) && is_numeric($aggregate->queues ?? null) ? (int) $aggregate->queues : 0,
                'latestFailedAt' => is_object($aggregate) && is_scalar($aggregate->latest_failed_at ?? null)
                    ? (string) $aggregate->latest_failed_at
                    : null,
            ],
            'empty' => false,
        ]);
    }

    public function moduleActivation(): JsonResponse
    {
        $status = $this->moduleActivationDiagnostics->status();

        return response()->json([
            'data' => [
                'label' => 'Module activation',
                'value' => $status['label'],
                'description' => $status['description'],
                'status' => $status['status'],
                'failedCount' => $status['failedCount'],
                'scheduledCount' => $status['scheduledCount'],
                'latestFailedPublicId' => $status['latestFailedPublicId'],
                'latestFailedModule' => $status['latestFailedModule'],
                'latestFailedAt' => $status['latestFailedAt'],
                'latestFailureReason' => $status['latestFailureReason'],
                'items' => $status['items'],
            ],
            'empty' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleStatusRow(ModuleDefinition $module, ?int $teamId): array
    {
        $key = $module->key()->value;
        $effective = $this->moduleActivation->effectiveState($key, $teamId);
        $issues = $this->moduleIssues($key);
        $status = $this->moduleStatus($effective->technicallyAvailable, $effective->effectiveEnabled, $issues);

        return [
            'key' => $key,
            'category' => $module->category()->value,
            'status' => $status,
            'technicallyAvailable' => $effective->technicallyAvailable,
            'globallyEnabled' => $effective->globallyEnabled,
            'teamEnabled' => $effective->teamEnabled,
            'effectiveEnabled' => $effective->effectiveEnabled,
            'source' => $effective->source,
            'requiredDependencies' => array_map(static fn (ModuleKey $dependency): string => $dependency->value, $module->requiredDependencies()),
            'optionalDependencies' => array_map(static fn (ModuleKey $dependency): string => $dependency->value, $module->optionalDependencies()),
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<array{severity: string, label: string, description: string, value?: int|string|null}>  $issues
     */
    private function moduleStatus(bool $technicallyAvailable, bool $effectiveEnabled, array $issues): string
    {
        if (! $technicallyAvailable) {
            return 'unavailable';
        }

        if (! $effectiveEnabled) {
            return 'inactive';
        }

        foreach ($issues as $issue) {
            if ($issue['severity'] === 'unhealthy') {
                return 'unhealthy';
            }
        }

        foreach ($issues as $issue) {
            if ($issue['severity'] === 'degraded') {
                return 'degraded';
            }
        }

        return 'healthy';
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function moduleIssues(string $moduleKey): array
    {
        return match ($moduleKey) {
            'authorization' => $this->authorizationIssues(),
            'files' => $this->fileIssues(),
            'health' => $this->healthIssues(),
            'identity' => $this->identityIssues(),
            'integrations' => $this->integrationIssues(),
            'managed_processes' => $this->managedProcessIssues(),
            'imports' => $this->importIssues(),
            default => [],
        };
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function authorizationIssues(): array
    {
        $issues = [];
        $moduleActivation = $this->moduleActivationDiagnostics->status();
        $failedSchedules = is_numeric($moduleActivation['failedCount'] ?? null) ? (int) $moduleActivation['failedCount'] : 0;
        $scheduledChanges = is_numeric($moduleActivation['scheduledCount'] ?? null) ? (int) $moduleActivation['scheduledCount'] : 0;

        if ($failedSchedules > 0) {
            $issues[] = [
                'severity' => 'unhealthy',
                'label' => 'Failed activation schedules',
                'description' => 'Scheduled module activation changes failed and need review.',
                'value' => $failedSchedules,
            ];
        }

        if ($scheduledChanges > 0) {
            $issues[] = [
                'severity' => 'info',
                'label' => 'Scheduled activation changes',
                'description' => 'Module activation changes are waiting for their effective time.',
                'value' => $scheduledChanges,
            ];
        }

        return $issues;
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function fileIssues(): array
    {
        $rows = DB::table(FilesDatabaseTable::FILE_OBJECTS)
            ->selectRaw('scan_state, count(*) as total')
            ->whereNull('deleted_at')
            ->whereNull('acknowledged_at')
            ->groupBy('scan_state')
            ->pluck('total', 'scan_state');

        $blocked = $this->intValue($rows['infected'] ?? null) + $this->intValue($rows['failed'] ?? null) + $this->intValue($rows['unsupported'] ?? null);

        if ($blocked === 0) {
            return [];
        }

        return [[
            'severity' => 'degraded',
            'label' => 'Blocked files',
            'description' => 'File scan states are blocking file use and need review.',
            'value' => $blocked,
        ]];
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function healthIssues(): array
    {
        $payload = $this->readiness->check()->toAdminArray();

        if ($payload['status'] === 'healthy') {
            return [];
        }

        return [[
            'severity' => $payload['status'] === 'unhealthy' ? 'unhealthy' : 'degraded',
            'label' => 'Readiness',
            'description' => sprintf('%d blocking and %d degraded readiness issue(s).', $payload['blocking']['failed'], $payload['degraded']['failed']),
            'value' => $payload['blocking']['failed'] + $payload['degraded']['failed'],
        ]];
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function identityIssues(): array
    {
        $rejections = (int) DB::table(IdentityDatabaseTable::RATE_LIMIT_REJECTIONS)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($rejections === 0) {
            return [];
        }

        return [[
            'severity' => 'info',
            'label' => 'Rate-limit rejections',
            'description' => 'Rate limits rejected requests during the last 24 hours.',
            'value' => $rejections,
        ]];
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function integrationIssues(): array
    {
        $openCircuits = (int) DB::table(IntegrationsDatabaseTable::CIRCUIT_BREAKERS)->where('state', 'open')->count();
        $failedRuns = (int) DB::table(IntegrationsDatabaseTable::SYNC_RUNS)->where('status', 'failed')->where('started_at', '>=', now()->subDay())->count();
        $issues = [];

        if ($openCircuits > 0) {
            $issues[] = [
                'severity' => 'unhealthy',
                'label' => 'Open circuits',
                'description' => 'Integration circuit breakers are open.',
                'value' => $openCircuits,
            ];
        }

        if ($failedRuns > 0) {
            $issues[] = [
                'severity' => 'degraded',
                'label' => 'Failed sync runs',
                'description' => 'Integration synchronization runs failed during the last 24 hours.',
                'value' => $failedRuns,
            ];
        }

        return $issues;
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function managedProcessIssues(): array
    {
        $active = (int) DB::table(ManagedProcessesDatabaseTable::RUNS)->whereIn('status', ['draft', 'queued', 'running', 'waiting'])->count();
        $failed = (int) $this->unacknowledgedManagedProcessRunsQuery()->where('process_runs.status', 'failed')->where('process_runs.created_at', '>=', now()->subDay())->count();
        $warnings = (int) $this->unacknowledgedManagedProcessRunsQuery()->where('process_runs.status', 'succeeded_with_warnings')->where('process_runs.created_at', '>=', now()->subDay())->count();
        $issues = [];

        if ($failed > 0) {
            $issues[] = ['severity' => 'unhealthy', 'label' => 'Failed process runs', 'description' => 'Managed process runs failed during the last 24 hours.', 'value' => $failed];
        }

        if ($warnings > 0) {
            $issues[] = ['severity' => 'degraded', 'label' => 'Process warnings', 'description' => 'Managed process runs completed with warnings during the last 24 hours.', 'value' => $warnings];
        }

        if ($active > 0) {
            $issues[] = ['severity' => 'info', 'label' => 'Active process runs', 'description' => 'Managed process runs are currently active or queued.', 'value' => $active];
        }

        return $issues;
    }

    private function unacknowledgedManagedProcessRunsQuery(): Builder
    {
        return DB::table(ManagedProcessesDatabaseTable::RUNS.' as process_runs')
            ->leftJoin(ManagedProcessesDatabaseTable::RUN_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.process_run_id', '=', 'process_runs.id')
            ->whereNull('acknowledgements.process_run_id');
    }

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    private function importIssues(): array
    {
        $rowWarnings = (int) DB::table(ImportsDatabaseTable::ROW_ERRORS)->where('severity', 'warning')->count();
        $rowErrors = (int) DB::table(ImportsDatabaseTable::ROW_ERRORS)->where('severity', 'error')->count();

        if ($rowErrors > 0) {
            return [['severity' => 'degraded', 'label' => 'Import row errors', 'description' => 'Import row errors are available for operator review.', 'value' => $rowErrors]];
        }

        if ($rowWarnings > 0) {
            return [['severity' => 'info', 'label' => 'Import row warnings', 'description' => 'Import row warnings are available for operator review.', 'value' => $rowWarnings]];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(Request $request): array
    {
        $release = $this->releasePayload();
        $readiness = $this->readiness->check()->toAdminArray();
        $moduleActivation = $this->moduleActivationDiagnostics->status();
        $externalMechanismChecks = array_values(array_filter(
            $readiness['checks'],
            static fn (array $check): bool => in_array($check['key'], ['postgresql', 'redis', 'storage', 'meilisearch', 'clamav', 'chromium-pdf'], true),
        ));
        $teamId = $this->activeTeamId($request);
        $modules = array_map(fn (ModuleDefinition $module): array => $this->moduleStatusRow($module, $teamId), $this->moduleRegistry->all());
        $activeModules = count(array_filter($modules, static fn (array $module): bool => $module['effectiveEnabled'] === true));
        $modulesNeedingAttention = count(array_filter(
            $modules,
            static fn (array $module): bool => in_array($module['status'], ['degraded', 'unhealthy', 'unavailable'], true),
        ));
        $blockingReadiness = $this->intValue(data_get($readiness, 'blocking.failed'));
        $degradedReadiness = $this->intValue(data_get($readiness, 'degraded.failed'));

        return [
            'generatedAt' => now()->toIso8601String(),
            'release' => $release,
            'externalMechanisms' => [
                'status' => $this->aggregateStatus($externalMechanismChecks),
                'checkedAt' => $readiness['checked_at'],
                'blockingFailures' => $blockingReadiness,
                'degradedFailures' => $degradedReadiness,
                'items' => $externalMechanismChecks,
            ],
            'modules' => [
                'active' => $activeModules,
                'total' => count($modules),
                'needingAttention' => $modulesNeedingAttention,
                'failedActivationSchedules' => $this->intValue($moduleActivation['failedCount'] ?? null),
                'scheduledActivationChanges' => $this->intValue($moduleActivation['scheduledCount'] ?? null),
                'items' => array_map(
                    static fn (array $module): array => [
                        'key' => $module['key'],
                        'category' => $module['category'],
                        'status' => $module['status'],
                        'effectiveEnabled' => $module['effectiveEnabled'],
                        'technicallyAvailable' => $module['technicallyAvailable'],
                        'issueCount' => self::issueCount($module['issues'] ?? null),
                        'issue' => self::firstIssue($module['issues'] ?? null),
                    ],
                    $modules,
                ),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function releasePayload(): array
    {
        $deployedAt = config('atlas.release.deployed_at');
        $deployedBy = config('atlas.release.deployed_by');
        $source = config('atlas.release.source');

        return [
            'version' => config()->string('atlas.release.version'),
            'id' => config()->string('atlas.release.id'),
            'environment' => config()->string('app.env'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'timezone' => config()->string('app.timezone'),
            'runtime' => PHP_SAPI,
            'deployedAt' => is_scalar($deployedAt) ? (string) $deployedAt : null,
            'deployedBy' => is_scalar($deployedBy) ? (string) $deployedBy : null,
            'source' => is_scalar($source) ? (string) $source : null,
        ];
    }

    private function activeTeamId(Request $request): ?int
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($teamPublicId)) {
            return null;
        }

        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($teamId) ? (int) $teamId : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function issueCount(mixed $issues): int
    {
        return is_array($issues) ? count($issues) : 0;
    }

    private static function firstIssue(mixed $issues): mixed
    {
        return is_array($issues) ? ($issues[0] ?? null) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     */
    private function aggregateStatus(array $checks): string
    {
        $statuses = array_map(static fn (array $check): string => is_string($check['status'] ?? null) ? $check['status'] : 'unknown', $checks);

        if (in_array('unhealthy', $statuses, true) || in_array('unavailable', $statuses, true) || in_array('failed', $statuses, true)) {
            return 'unhealthy';
        }

        if (in_array('degraded', $statuses, true)) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * @return array{elementKey: string, reason: string}
     */
    private function availabilityEntry(string $elementKey, ModuleAccessRequest $request): array
    {
        $decision = $this->moduleGate->inspect($request);

        return [
            'elementKey' => $elementKey,
            'reason' => match ($decision->denialReason) {
                null => 'available',
                ModuleAccessDenialReason::PermissionDenied => 'permission-denied',
                ModuleAccessDenialReason::InvalidActiveTeam => 'active-team-required',
                default => 'module-inactive',
            },
        ];
    }
}
