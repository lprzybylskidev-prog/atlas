<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers\Modules;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationException;
use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final readonly class ModuleActivationController
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleActivationService $activation,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::MODULES);
        $state = TableState::fromRequest($request, $definition);
        $filters = $this->filters($request);
        [$userId, $teamId] = $this->context->userTeam($request);
        $activeTeamId = $this->activeTeamId($request);
        $rows = array_map(fn (ModuleDefinition $module): array => $this->moduleRow($module, $activeTeamId), $this->registry->all());
        $result = $this->tables->process($this->filteredRows($rows, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $result->rows,
            'filterOptions' => [
                'categories' => array_map(static fn (ModuleCategory $category): string => $category->value, ModuleCategory::cases()),
                'sources' => $this->teamStateSources($rows),
            ],
            'table' => $table,
        ]);
    }

    public function show(string $module): Response
    {
        if (! $this->registry->has(new ModuleKey($module))) {
            abort(404);
        }

        $definition = $this->registry->get(new ModuleKey($module));

        return Inertia::render('Admin/Modules/Show', [
            'module' => $this->moduleRow($definition, null),
            'teams' => $this->teamRows($module),
            'history' => $this->historyRows($module),
            'schedules' => $this->scheduleRows($module),
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }

    public function createTeamConfiguration(Request $request, string $module): Response
    {
        if (! $this->registry->has(new ModuleKey($module))) {
            abort(404);
        }

        $definition = $this->registry->get(new ModuleKey($module));

        if ($definition->category() === ModuleCategory::Core || ! $definition->supportsTeamActivation()) {
            abort(404);
        }

        $teams = $this->teamRows($module);
        $requestedTeam = $request->query('team');
        $selectedTeamPublicId = is_string($requestedTeam) && $requestedTeam !== ''
            ? $requestedTeam
            : $this->activeTeamPublicId($request);
        $selectedTeam = $this->selectedTeamRow($teams, $selectedTeamPublicId);
        $selectedTeamId = is_array($selectedTeam) ? $this->teamId($this->scalarString($selectedTeam['publicId'] ?? '')) : null;

        return Inertia::render('Admin/Modules/TeamConfiguration', [
            'module' => $this->moduleRow($definition, $selectedTeamId),
            'selectedTeamPublicId' => is_array($selectedTeam) ? $this->scalarString($selectedTeam['publicId'] ?? '') : null,
            'teams' => $teams,
            'history' => $this->historyRows($module),
            'schedules' => $this->scheduleRows($module),
        ]);
    }

    public function updateGlobal(Request $request, string $module): RedirectResponse
    {
        $validated = $this->validated($request, [
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->attemptChange($request, new ModuleActivationChange(
            moduleKey: $module,
            scope: ModuleActivationScope::Global,
            enabled: $this->boolValue($validated, 'enabled'),
            reason: $this->stringValue($validated, 'reason'),
            actorUserId: $this->actorUserId($request),
            expectedVersion: $this->intValue($validated, 'version'),
        ));

        return redirect()->route('admin.modules.show', ['module' => $module])->with('flash.messages', [
            FlashMessage::success('flash.modules.global_activation_updated'),
        ]);
    }

    public function updateTeam(Request $request, string $module, string $team): RedirectResponse
    {
        $validated = $this->validated($request, [
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
        $teamId = $this->teamId($team);

        if ($teamId === null) {
            abort(404);
        }

        $this->attemptChange($request, new ModuleActivationChange(
            moduleKey: $module,
            scope: ModuleActivationScope::Team,
            enabled: $this->boolValue($validated, 'enabled'),
            reason: $this->stringValue($validated, 'reason'),
            actorUserId: $this->actorUserId($request),
            teamId: $teamId,
            expectedVersion: $this->intValue($validated, 'version'),
        ));

        return redirect()->back()->with('flash.messages', [
            FlashMessage::success('flash.modules.team_override_updated'),
        ]);
    }

    public function clearTeam(Request $request, string $module, string $team): RedirectResponse
    {
        $validated = $this->validated($request, [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $teamId = $this->teamId($team);
        $actorUserId = $this->actorUserId($request);

        if ($teamId === null || ! is_int($actorUserId)) {
            abort(404);
        }

        $reason = $this->stringValue($validated, 'reason');
        $this->activation->clearTeamOverride($module, $teamId, $actorUserId, $reason);
        $this->recordAudit($request, 'module.team_override_cleared', 'succeeded', $module, $reason);

        return redirect()->back()->with('flash.messages', [
            FlashMessage::success('flash.modules.team_override_cleared'),
        ]);
    }

    public function scheduleGlobal(Request $request, string $module): RedirectResponse
    {
        $validated = $this->validated($request, [
            'enabled' => ['required', 'boolean'],
            'effective_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $this->attemptSchedule($request, new ModuleActivationChange(
            moduleKey: $module,
            scope: ModuleActivationScope::Global,
            enabled: $this->boolValue($validated, 'enabled'),
            reason: $this->stringValue($validated, 'reason'),
            actorUserId: $this->actorUserId($request),
        ), CarbonImmutable::parse($this->stringValue($validated, 'effective_at'), 'UTC'));

        return redirect()->back()->with('flash.messages', [
            FlashMessage::success('flash.modules.global_activation_scheduled'),
        ]);
    }

    public function scheduleTeam(Request $request, string $module, string $team): RedirectResponse
    {
        $validated = $this->validated($request, [
            'enabled' => ['required', 'boolean'],
            'effective_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $teamId = $this->teamId($team);

        if ($teamId === null) {
            abort(404);
        }

        $this->attemptSchedule($request, new ModuleActivationChange(
            moduleKey: $module,
            scope: ModuleActivationScope::Team,
            enabled: $this->boolValue($validated, 'enabled'),
            reason: $this->stringValue($validated, 'reason'),
            actorUserId: $this->actorUserId($request),
            teamId: $teamId,
        ), CarbonImmutable::parse($this->stringValue($validated, 'effective_at'), 'UTC'));

        return redirect()->back()->with('flash.messages', [
            FlashMessage::success('flash.modules.team_activation_scheduled'),
        ]);
    }

    public function cancelSchedule(Request $request, string $module, string $schedule): RedirectResponse
    {
        $validated = $this->validated($request, [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $actorUserId = $this->actorUserId($request);

        if (! is_int($actorUserId)) {
            abort(404);
        }

        $reason = $this->stringValue($validated, 'reason');
        $this->activation->cancelSchedule($schedule, $actorUserId, $reason);
        $this->recordAudit($request, 'module.schedule_cancelled', 'succeeded', $module, $reason, [
            'schedule_public_id' => $schedule,
        ]);

        return redirect()->back()->with('flash.messages', [
            FlashMessage::success('flash.modules.schedule_cancelled'),
        ]);
    }

    private function attemptChange(Request $request, ModuleActivationChange $change): void
    {
        try {
            $this->activation->change($change);
            $this->recordAudit(
                $request,
                $change->scope === ModuleActivationScope::Global ? 'module.global_activation_changed' : 'module.team_activation_changed',
                'succeeded',
                $change->moduleKey,
                $change->reason,
            );
        } catch (ModuleActivationException $exception) {
            $this->recordAudit($request, 'module.activation_rejected', 'rejected', $change->moduleKey, $change->reason, [
                'error' => $exception->getMessage(),
            ]);

            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }
    }

    private function attemptSchedule(Request $request, ModuleActivationChange $change, CarbonImmutable $effectiveAt): void
    {
        try {
            $publicId = $this->activation->schedule($change, $effectiveAt);
            $this->recordAudit($request, 'module.activation_scheduled', 'succeeded', $change->moduleKey, $change->reason, [
                'schedule_public_id' => $publicId,
                'scope' => $change->scope->value,
                'effective_at' => $effectiveAt->toIso8601String(),
            ]);
        } catch (ModuleActivationException $exception) {
            $this->recordAudit($request, 'module.schedule_rejected', 'rejected', $change->moduleKey, $change->reason, [
                'error' => $exception->getMessage(),
            ]);

            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleRow(ModuleDefinition $module, ?int $teamId): array
    {
        $effective = $this->activation->effectiveState($module->key()->value, $teamId);
        $scheduledChangesCount = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('module_key', $module->key()->value)
            ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
            ->count();

        return [
            'moduleKey' => $module->key()->value,
            'category' => $module->category()->value,
            'technicallyAvailable' => $effective->technicallyAvailable,
            'globallyEnabled' => $effective->globallyEnabled,
            'teamEnabled' => $effective->teamEnabled,
            'effectiveEnabled' => $effective->effectiveEnabled,
            'teamStateSource' => $effective->source,
            'globalVersion' => $effective->globalVersion,
            'teamVersion' => $effective->teamVersion,
            'supportsGlobalActivation' => $module->supportsGlobalActivation(),
            'supportsTeamActivation' => $module->supportsTeamActivation(),
            'scheduledChangesCount' => $scheduledChangesCount,
            'requiredDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->requiredDependencies())),
            'optionalDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->optionalDependencies())),
            'readOnly' => $module->category() === ModuleCategory::Core,
        ];
    }

    /**
     * @return array{category: string, source: string, availability: string, global: string, team: string, effective: string, globalSupport: string, teamSupport: string, scheduled: string}
     */
    private function filters(Request $request): array
    {
        $category = $request->query('category');
        $source = $request->query('source');

        return [
            'category' => is_string($category) && in_array($category, array_map(static fn (ModuleCategory $case): string => $case->value, ModuleCategory::cases()), true) ? $category : 'all',
            'source' => is_string($source) && in_array($source, ['global', 'team'], true) ? $source : 'all',
            'availability' => $this->yesNoFilter($request, 'availability'),
            'global' => $this->yesNoFilter($request, 'global'),
            'team' => $this->yesNoFilter($request, 'team'),
            'effective' => $this->yesNoFilter($request, 'effective'),
            'globalSupport' => $this->yesNoFilter($request, 'globalSupport'),
            'teamSupport' => $this->yesNoFilter($request, 'teamSupport'),
            'scheduled' => $this->yesNoFilter($request, 'scheduled'),
        ];
    }

    private function yesNoFilter(Request $request, string $key): string
    {
        $value = $request->query($key);

        return in_array($value, ['yes', 'no'], true) ? $value : 'all';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{category: string, source: string, availability: string, global: string, team: string, effective: string, globalSupport: string, teamSupport: string, scheduled: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['category'] !== 'all' && ($row['category'] ?? null) !== $filters['category']) {
                return false;
            }

            if ($filters['source'] !== 'all' && ($row['teamStateSource'] ?? null) !== $filters['source']) {
                return false;
            }

            foreach ([
                'availability' => 'technicallyAvailable',
                'global' => 'globallyEnabled',
                'team' => 'teamEnabled',
                'effective' => 'effectiveEnabled',
                'globalSupport' => 'supportsGlobalActivation',
                'teamSupport' => 'supportsTeamActivation',
            ] as $filterKey => $rowKey) {
                if ($filters[$filterKey] !== 'all' && ($row[$rowKey] ?? false) !== ($filters[$filterKey] === 'yes')) {
                    return false;
                }
            }

            if ($filters['scheduled'] === 'yes' && self::scalarIntValue($row['scheduledChangesCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['scheduled'] === 'no' && self::scalarIntValue($row['scheduledChangesCount'] ?? 0) > 0) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function teamStateSources(array $rows): array
    {
        $sources = [];

        foreach ($rows as $row) {
            $source = $row['teamStateSource'] ?? null;

            if (is_string($source) && $source !== '') {
                $sources[$source] = $source;
            }
        }

        sort($sources);

        return $sources;
    }

    /**
     * @param  list<array<string, mixed>>  $teams
     * @return array<string, mixed>|null
     */
    private function selectedTeamRow(array $teams, ?string $teamPublicId): ?array
    {
        foreach ($teams as $team) {
            if (($team['publicId'] ?? null) === $teamPublicId) {
                return $team;
            }
        }

        return $teams[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamRows(string $module): array
    {
        $rows = [];

        foreach (DB::table(DatabaseTable::TEAMS)->orderBy('display_name')->orderBy('name')->get(['id', 'public_id', 'name', 'display_name', 'is_active']) as $team) {
            $values = get_object_vars($team);
            $teamId = is_numeric($values['id'] ?? null) ? (int) $values['id'] : null;

            if ($teamId === null) {
                continue;
            }

            $effective = $this->activation->effectiveState($module, $teamId);
            $rows[] = [
                'publicId' => $this->scalarString($values['public_id'] ?? ''),
                'name' => $this->teamDisplayName($team),
                'isActive' => (bool) $values['is_active'],
                'teamEnabled' => $effective->teamEnabled,
                'effectiveEnabled' => $effective->effectiveEnabled,
                'source' => $effective->source,
                'version' => $effective->teamVersion,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historyRows(string $module): array
    {
        $rows = DB::table(DatabaseTable::MODULE_ACTIVATION_HISTORY)
            ->leftJoin(DatabaseTable::TEAMS, 'module_activation_history.team_id', '=', 'teams.id')
            ->where('module_activation_history.module_key', $module)
            ->orderByDesc('module_activation_history.effective_at')
            ->limit(25)
            ->get([
                'module_activation_history.scope',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'module_activation_history.previous_enabled',
                'module_activation_history.new_enabled',
                'module_activation_history.source',
                'module_activation_history.reason',
                'module_activation_history.effective_at',
            ])
            ->map(static fn (object $row): array => [
                'scope' => self::rowString($row, 'scope'),
                'teamPublicId' => is_string($row->team_public_id ?? null) ? $row->team_public_id : null,
                'teamName' => is_string($row->team_name ?? null) ? $row->team_name : null,
                'previousEnabled' => $row->previous_enabled === null ? null : (bool) $row->previous_enabled,
                'newEnabled' => (bool) $row->new_enabled,
                'source' => self::rowString($row, 'source'),
                'reason' => self::rowString($row, 'reason'),
                'effectiveAt' => self::rowString($row, 'effective_at'),
            ])
            ->values()
            ->all();

        return array_values($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scheduleRows(string $module): array
    {
        $rows = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->leftJoin(DatabaseTable::TEAMS, 'module_activation_schedules.team_id', '=', 'teams.id')
            ->where('module_activation_schedules.module_key', $module)
            ->orderByDesc('module_activation_schedules.effective_at')
            ->limit(25)
            ->get([
                'module_activation_schedules.public_id',
                'module_activation_schedules.scope',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'module_activation_schedules.target_enabled',
                'module_activation_schedules.status',
                'module_activation_schedules.reason',
                'module_activation_schedules.effective_at',
            ])
            ->map(static fn (object $row): array => [
                'publicId' => self::rowString($row, 'public_id'),
                'scope' => self::rowString($row, 'scope'),
                'teamPublicId' => is_string($row->team_public_id ?? null) ? $row->team_public_id : null,
                'teamName' => is_string($row->team_name ?? null) ? $row->team_name : null,
                'targetEnabled' => (bool) $row->target_enabled,
                'status' => self::rowString($row, 'status'),
                'reason' => self::rowString($row, 'reason'),
                'effectiveAt' => self::rowString($row, 'effective_at'),
            ])
            ->values()
            ->all();

        return array_values($rows);
    }

    private function activeTeamId(Request $request): ?int
    {
        $teamPublicId = $this->activeTeamPublicId($request);

        return is_string($teamPublicId) ? $this->teamId($teamPublicId) : null;
    }

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) && $teamPublicId !== '' ? $teamPublicId : null;
    }

    private function teamId(string $teamPublicId): ?int
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($teamId) ? (int) $teamId : null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $validated = $request->validate($rules);

        return $this->stringKeyArray($validated);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function boolValue(array $values, string $key): bool
    {
        return (bool) ($values[$key] ?? false);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function intValue(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private static function scalarIntValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function actorUserId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function teamDisplayName(object $team): string
    {
        $displayName = $this->scalarString($team->display_name ?? '');

        return $displayName === '' ? $this->scalarString($team->name ?? '') : $displayName;
    }

    private static function rowString(object $row, string $property): string
    {
        $value = $row->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAudit(Request $request, string $action, string $result, string $module, string $reason, array $metadata = []): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->audit->record(new AuditEvent(
            module: 'modules',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: 'module',
            targetPublicId: null,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            reason: $reason,
            metadata: ['module_key' => $module, ...$metadata],
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }
}
