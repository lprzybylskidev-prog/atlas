<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers\Modules;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationException;
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
        [$userId, $teamId] = $this->context->userTeam($request);
        $activeTeamId = $this->activeTeamId($request);
        $rows = array_map(fn (ModuleDefinition $module): array => $this->moduleRow($module, $activeTeamId), $this->registry->all());
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $result->rows,
            'table' => $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults()),
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

        return redirect()->route('admin.modules.show', ['module' => $module])->with('success', 'Global module activation was updated.');
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

        return redirect()->back()->with('success', 'Team module override was updated.');
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

        return redirect()->back()->with('success', 'Team module override was cleared.');
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

        return redirect()->back()->with('success', 'Global module activation change was scheduled.');
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

        return redirect()->back()->with('success', 'Team module activation change was scheduled.');
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

        return redirect()->back()->with('success', 'Module activation schedule was cancelled.');
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
            'requiredDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->requiredDependencies())),
            'optionalDependencies' => implode(', ', array_map(static fn (ModuleKey $key): string => $key->value, $module->optionalDependencies())),
            'readOnly' => $module->category() === ModuleCategory::Core,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamRows(string $module): array
    {
        $rows = [];

        foreach (DB::table(DatabaseTable::TEAMS)->orderBy('name')->get(['id', 'public_id', 'name', 'is_active']) as $team) {
            $values = get_object_vars($team);
            $teamId = is_numeric($values['id'] ?? null) ? (int) $values['id'] : null;

            if ($teamId === null) {
                continue;
            }

            $effective = $this->activation->effectiveState($module, $teamId);
            $rows[] = [
                'publicId' => $this->scalarString($values['public_id'] ?? ''),
                'name' => $this->scalarString($values['name'] ?? ''),
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
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) ? $this->teamId($teamPublicId) : null;
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

    private function actorUserId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
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
