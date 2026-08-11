<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Modules\Activation;

use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\EffectiveModuleState;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationException;
use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Contracts\ModuleTechnicalAvailability;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final readonly class DatabaseModuleActivationService implements ModuleActivationService
{
    private const CACHE_PREFIX = 'atlas:module-activation:';

    public function __construct(
        private ModuleRegistry $registry,
        private ConnectionInterface $database,
        private ModuleDeactivationGuardRegistry $deactivationGuards,
        private AuditRecorder $audit,
        private TeamLookup $teams,
        private ModuleTechnicalAvailability $technicalAvailability,
    ) {}

    public function effectiveState(string $moduleKey, ?int $teamId = null): EffectiveModuleState
    {
        $cached = Cache::get($this->cacheKey($moduleKey, $teamId));

        if ($this->hasStringKeys($cached)) {
            return $this->stateFromArray($cached);
        }

        $state = $this->resolveEffectiveState($moduleKey, $teamId);
        Cache::put($this->cacheKey($moduleKey, $teamId), $this->stateToArray($state), now()->addMinutes(10));

        return $state;
    }

    public function change(ModuleActivationChange $change): EffectiveModuleState
    {
        if (! $change->enabled) {
            $this->recordDeactivationAudit($change, 'attempted');
        }

        try {
            $this->validateChange($change);
        } catch (\Throwable $exception) {
            if (! $change->enabled) {
                $this->recordDeactivationAudit($change, 'rejected', $exception->getMessage());
            }

            throw $exception;
        }

        return $this->database->transaction(function () use ($change): EffectiveModuleState {
            $previous = $this->effectiveState($change->moduleKey, $change->teamId);
            $now = $change->effectiveAt ?? CarbonImmutable::now('UTC');
            $table = $change->scope === ModuleActivationScope::Global ? DatabaseTable::MODULE_GLOBAL_STATES : DatabaseTable::MODULE_TEAM_STATES;
            $lookup = ['module_key' => $change->moduleKey];

            if ($change->scope === ModuleActivationScope::Team) {
                $lookup['team_id'] = $change->teamId;
            }

            $row = $this->database->table($table)->where($lookup)->lockForUpdate()->first();
            $nextVersion = 1;

            if (is_object($row)) {
                $values = get_object_vars($row);
                $currentVersion = is_numeric($values['version'] ?? null) ? (int) $values['version'] : 1;

                if ($change->expectedVersion !== null && $change->expectedVersion !== $currentVersion) {
                    throw ModuleActivationException::staleState($change->moduleKey);
                }

                $nextVersion = $currentVersion + 1;
                $this->database->table($table)->where('id', $values['id'])->update($this->statePayload($change, $now, $nextVersion));
            } else {
                $this->database->table($table)->insert(array_merge($lookup, $this->statePayload($change, $now, $nextVersion), [
                    'created_at' => $now,
                ]));
            }

            $this->database->table(DatabaseTable::MODULE_ACTIVATION_HISTORY)->insert([
                'module_key' => $change->moduleKey,
                'scope' => $change->scope->value,
                'team_id' => $change->teamId,
                'previous_enabled' => $change->scope === ModuleActivationScope::Global ? $previous->globallyEnabled : $previous->teamEnabled,
                'new_enabled' => $change->enabled,
                'source' => $change->source->value,
                'schedule_id' => $change->scheduleId,
                'actor_user_id' => $change->actorUserId,
                'reason' => $change->reason,
                'effective_at' => $now,
                'correlation_id' => $this->correlationId(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->invalidate($change->moduleKey, $change->scope === ModuleActivationScope::Team ? $change->teamId : null);

            if (! $change->enabled) {
                $this->recordDeactivationAudit($change, 'succeeded');
            }

            return $this->resolveEffectiveState($change->moduleKey, $change->teamId);
        });
    }

    public function schedule(ModuleActivationChange $change, CarbonImmutable $effectiveAt): string
    {
        $this->validateChange($change);

        return $this->database->transaction(function () use ($change, $effectiveAt): string {
            $conflict = $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
                ->where('module_key', $change->moduleKey)
                ->where('scope', $change->scope->value)
                ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
                ->where('effective_at', $effectiveAt)
                ->when($change->scope === ModuleActivationScope::Team, fn ($query) => $query->where('team_id', $change->teamId))
                ->when($change->scope === ModuleActivationScope::Global, fn ($query) => $query->whereNull('team_id'))
                ->exists();

            if ($conflict) {
                throw ModuleActivationException::conflictingSchedule($change->moduleKey);
            }

            $publicId = (string) Str::ulid();
            $now = CarbonImmutable::now('UTC');

            $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->insert([
                'public_id' => $publicId,
                'module_key' => $change->moduleKey,
                'scope' => $change->scope->value,
                'team_id' => $change->teamId,
                'target_enabled' => $change->enabled,
                'effective_at' => $effectiveAt,
                'status' => ModuleActivationScheduleStatus::Scheduled->value,
                'creator_user_id' => $change->actorUserId,
                'reason' => $change->reason,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $publicId;
        });
    }

    public function cancelSchedule(string $schedulePublicId, int $actorUserId, string $reason): void
    {
        $now = CarbonImmutable::now('UTC');

        $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('public_id', $schedulePublicId)
            ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
            ->update([
                'status' => ModuleActivationScheduleStatus::Cancelled->value,
                'cancellation_actor_user_id' => $actorUserId,
                'cancellation_reason' => $reason,
                'updated_at' => $now,
            ]);
    }

    public function clearTeamOverride(string $moduleKey, int $teamId, int $actorUserId, string $reason): EffectiveModuleState
    {
        return $this->database->transaction(function () use ($moduleKey, $teamId, $actorUserId, $reason): EffectiveModuleState {
            $previous = $this->effectiveState($moduleKey, $teamId);
            $now = CarbonImmutable::now('UTC');
            $deleted = $this->database->table(DatabaseTable::MODULE_TEAM_STATES)
                ->where('module_key', $moduleKey)
                ->where('team_id', $teamId)
                ->delete();

            if ($deleted > 0) {
                $next = $this->resolveEffectiveState($moduleKey, null);
                $this->database->table(DatabaseTable::MODULE_ACTIVATION_HISTORY)->insert([
                    'module_key' => $moduleKey,
                    'scope' => ModuleActivationScope::Team->value,
                    'team_id' => $teamId,
                    'previous_enabled' => $previous->teamEnabled,
                    'new_enabled' => $next->globallyEnabled,
                    'source' => ModuleActivationSource::Manual->value,
                    'actor_user_id' => $actorUserId,
                    'reason' => $reason,
                    'effective_at' => $now,
                    'correlation_id' => $this->correlationId(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->invalidate($moduleKey, $teamId);

            return $this->resolveEffectiveState($moduleKey, $teamId);
        });
    }

    public function applyDueSchedules(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');
        $applied = 0;
        $rows = $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
            ->where('effective_at', '<=', $now)
            ->orderBy('effective_at')
            ->get();

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $id = is_numeric($values['id'] ?? null) ? (int) $values['id'] : null;

            if ($id === null) {
                continue;
            }

            try {
                $this->change(new ModuleActivationChange(
                    moduleKey: $this->stringValue($values['module_key'] ?? ''),
                    scope: ModuleActivationScope::from($this->stringValue($values['scope'] ?? '')),
                    enabled: (bool) $values['target_enabled'],
                    reason: $this->stringValue($values['reason'] ?? ''),
                    actorUserId: is_numeric($values['creator_user_id'] ?? null) ? (int) $values['creator_user_id'] : null,
                    teamId: is_numeric($values['team_id'] ?? null) ? (int) $values['team_id'] : null,
                    source: ModuleActivationSource::Scheduled,
                    scheduleId: $id,
                    effectiveAt: CarbonImmutable::parse($this->stringValue($values['effective_at'] ?? ''), 'UTC'),
                ));

                $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->where('id', $id)->update([
                    'status' => ModuleActivationScheduleStatus::Applied->value,
                    'updated_at' => $now,
                ]);
                $applied++;
            } catch (\Throwable $exception) {
                $this->database->table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)->where('id', $id)->update([
                    'status' => ModuleActivationScheduleStatus::Failed->value,
                    'failure_reason' => $exception->getMessage(),
                    'updated_at' => $now,
                ]);

                $this->audit->record(new AuditEvent(
                    module: 'authorization',
                    action: 'module.schedule_failed',
                    result: 'failed',
                    source: 'scheduler',
                    targetType: 'module_activation_schedule',
                    targetPublicId: $this->stringValue($values['public_id'] ?? ''),
                    aggregateType: 'module',
                    aggregatePublicId: $this->stringValue($values['module_key'] ?? ''),
                    reason: $exception->getMessage(),
                    metadata: [
                        'module_key' => $this->stringValue($values['module_key'] ?? ''),
                        'scope' => $this->stringValue($values['scope'] ?? ''),
                        'schedule_id' => $id,
                    ],
                ));
            }
        }

        return $applied;
    }

    public function invalidate(string $moduleKey, ?int $teamId = null): void
    {
        Cache::forget($this->cacheKey($moduleKey));

        if ($teamId !== null) {
            Cache::forget($this->cacheKey($moduleKey, $teamId));

            return;
        }

        foreach ($this->teams->allInternalIds() as $id) {
            Cache::forget($this->cacheKey($moduleKey, $id));
        }
    }

    private function resolveEffectiveState(string $moduleKey, ?int $teamId): EffectiveModuleState
    {
        $definition = $this->moduleDefinition($moduleKey);
        $deployed = $definition !== null;
        $technicallyAvailable = $definition instanceof ModuleDefinition
            && $this->technicalAvailability->available($definition);
        $core = $definition?->category() === ModuleCategory::Core;
        $globalRow = $this->database->table(DatabaseTable::MODULE_GLOBAL_STATES)->where('module_key', $moduleKey)->first();
        $globalValues = is_object($globalRow) ? get_object_vars($globalRow) : [];
        $globalEnabled = $core || (array_key_exists('enabled', $globalValues) ? (bool) $globalValues['enabled'] : false);
        $teamEnabled = $globalEnabled;
        $source = 'global';
        $teamPublicId = null;
        $teamVersion = null;

        if ($teamId !== null) {
            $teamPublicId = $this->teams->publicIdForInternalId($teamId);
            $teamRow = $this->database->table(DatabaseTable::MODULE_TEAM_STATES)->where('module_key', $moduleKey)->where('team_id', $teamId)->first();

            if (is_object($teamRow)) {
                $teamValues = get_object_vars($teamRow);
                $teamEnabled = (bool) ($teamValues['enabled'] ?? false);
                $teamVersion = is_numeric($teamValues['version'] ?? null) ? (int) $teamValues['version'] : null;
                $source = 'team';
            }
        }

        return new EffectiveModuleState(
            moduleKey: $moduleKey,
            deployed: $deployed,
            technicallyAvailable: $technicallyAvailable,
            globallyEnabled: $globalEnabled,
            teamEnabled: $teamEnabled,
            effectiveEnabled: $deployed && $technicallyAvailable && $globalEnabled && $teamEnabled,
            source: $source,
            teamPublicId: $teamPublicId,
            reason: is_string($globalValues['reason'] ?? null) ? $globalValues['reason'] : null,
            globalVersion: is_numeric($globalValues['version'] ?? null) ? (int) $globalValues['version'] : null,
            teamVersion: $teamVersion,
        );
    }

    private function validateChange(ModuleActivationChange $change): void
    {
        $definition = $this->moduleDefinition($change->moduleKey);

        if (! $definition instanceof ModuleDefinition) {
            throw ModuleActivationException::unavailableModuleCannotBeActivated($change->moduleKey);
        }

        if (! $definition->supportsGlobalActivation() && $change->scope === ModuleActivationScope::Global) {
            throw ModuleActivationException::globalScopeNotSupported($change->moduleKey);
        }

        if ($change->scope === ModuleActivationScope::Team && ! $definition->supportsTeamActivation()) {
            throw ModuleActivationException::teamScopeNotSupported($change->moduleKey);
        }

        if ($definition->category() === ModuleCategory::Core && ! $change->enabled) {
            throw ModuleActivationException::coreModuleCannotBeDisabled($change->moduleKey);
        }

        if (! $change->enabled) {
            $this->assertNoEnabledRequiredDependents($change);

            $assessment = $this->deactivationGuards->assess(new ModuleDeactivationRequest(
                moduleKey: new ModuleKey($change->moduleKey),
                teamId: $change->teamId,
                requestedBy: $change->actorUserId === null ? 'system' : (string) $change->actorUserId,
            ));

            if (! $assessment->canDeactivate()) {
                throw ModuleActivationException::unsafeProcessesBlockDeactivation($change->moduleKey);
            }

            return;
        }

        if (! $this->registry->has(new ModuleKey($change->moduleKey)) || ! $this->technicalAvailability->available($definition)) {
            throw ModuleActivationException::unavailableModuleCannotBeActivated($change->moduleKey);
        }
    }

    private function assertNoEnabledRequiredDependents(ModuleActivationChange $change): void
    {
        foreach ($this->registry->requiredDependentsOf(new ModuleKey($change->moduleKey)) as $dependent) {
            $state = $this->effectiveState($dependent->key()->value, $change->teamId);

            $enabled = $change->scope === ModuleActivationScope::Global
                ? $state->globallyEnabled
                : $state->effectiveEnabled;

            if ($enabled) {
                throw ModuleActivationException::requiredDependentBlocksDeactivation(
                    $change->moduleKey,
                    $dependent->key()->value,
                );
            }
        }
    }

    private function recordDeactivationAudit(ModuleActivationChange $change, string $result, ?string $rejectionReason = null): void
    {
        $this->audit->record(new AuditEvent(
            module: 'authorization',
            action: $result === 'attempted' ? 'module.deactivation_attempted' : 'module.deactivation',
            result: $result === 'attempted' ? 'succeeded' : $result,
            source: $change->source->value,
            targetType: 'module',
            targetPublicId: $change->moduleKey,
            aggregateType: 'module',
            aggregatePublicId: $change->moduleKey,
            reason: $rejectionReason ?? $change->reason,
            metadata: [
                'module_key' => $change->moduleKey,
                'scope' => $change->scope->value,
                'team_id' => $change->teamId,
            ],
        ));
    }

    private function moduleDefinition(string $moduleKey): ?ModuleDefinition
    {
        $key = new ModuleKey($moduleKey);

        return $this->registry->has($key) ? $this->registry->get($key) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function statePayload(ModuleActivationChange $change, CarbonImmutable $now, int $version): array
    {
        return [
            'enabled' => $change->enabled,
            'enabled_from' => $change->enabled ? $now : null,
            'disabled_from' => $change->enabled ? null : $now,
            'actor_user_id' => $change->actorUserId,
            'reason' => $change->reason,
            'version' => $version,
            'updated_at' => $now,
        ];
    }

    private function cacheKey(string $moduleKey, ?int $teamId = null): string
    {
        return self::CACHE_PREFIX.$moduleKey.':'.($teamId === null ? 'global' : 'team:'.$teamId);
    }

    private function correlationId(): ?string
    {
        $value = Context::get('correlation_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function stateToArray(EffectiveModuleState $state): array
    {
        return [
            'moduleKey' => $state->moduleKey,
            'deployed' => $state->deployed,
            'technicallyAvailable' => $state->technicallyAvailable,
            'globallyEnabled' => $state->globallyEnabled,
            'teamEnabled' => $state->teamEnabled,
            'effectiveEnabled' => $state->effectiveEnabled,
            'source' => $state->source,
            'teamPublicId' => $state->teamPublicId,
            'reason' => $state->reason,
            'globalVersion' => $state->globalVersion,
            'teamVersion' => $state->teamVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function stateFromArray(array $state): EffectiveModuleState
    {
        return new EffectiveModuleState(
            moduleKey: $this->stringValue($state['moduleKey'] ?? ''),
            deployed: (bool) ($state['deployed'] ?? false),
            technicallyAvailable: (bool) ($state['technicallyAvailable'] ?? false),
            globallyEnabled: (bool) ($state['globallyEnabled'] ?? false),
            teamEnabled: (bool) ($state['teamEnabled'] ?? false),
            effectiveEnabled: (bool) ($state['effectiveEnabled'] ?? false),
            source: $this->stringValue($state['source'] ?? 'global'),
            teamPublicId: is_string($state['teamPublicId'] ?? null) ? $state['teamPublicId'] : null,
            reason: is_string($state['reason'] ?? null) ? $state['reason'] : null,
            globalVersion: is_numeric($state['globalVersion'] ?? null) ? (int) $state['globalVersion'] : null,
            teamVersion: is_numeric($state['teamVersion'] ?? null) ? (int) $state['teamVersion'] : null,
        );
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private function hasStringKeys(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
