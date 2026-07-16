<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Modules\Activation;

use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\EffectiveModuleState;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationException;
use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuardRegistry;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\ModuleCategory;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class DatabaseModuleActivationService implements ModuleActivationService
{
    private const CACHE_PREFIX = 'atlas:module-activation:';

    public function __construct(
        private ModuleRegistry $registry,
        private ConnectionInterface $database,
        private ModuleDeactivationGuardRegistry $deactivationGuards,
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
        $this->validateChange($change);

        return $this->database->transaction(function () use ($change): EffectiveModuleState {
            $previous = $this->effectiveState($change->moduleKey, $change->teamId);
            $now = $change->effectiveAt ?? CarbonImmutable::now('UTC');
            $table = $change->scope === ModuleActivationScope::Global ? 'module_global_states' : 'module_team_states';
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

            $this->database->table('module_activation_history')->insert([
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
                'correlation_id' => request()->headers->get('X-Request-Id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->invalidate($change->moduleKey, $change->scope === ModuleActivationScope::Team ? $change->teamId : null);

            return $this->resolveEffectiveState($change->moduleKey, $change->teamId);
        });
    }

    public function schedule(ModuleActivationChange $change, CarbonImmutable $effectiveAt): string
    {
        $this->validateChange($change);

        return $this->database->transaction(function () use ($change, $effectiveAt): string {
            $conflict = $this->database->table('module_activation_schedules')
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

            $this->database->table('module_activation_schedules')->insert([
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

        $this->database->table('module_activation_schedules')
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
            $deleted = $this->database->table('module_team_states')
                ->where('module_key', $moduleKey)
                ->where('team_id', $teamId)
                ->delete();

            if ($deleted > 0) {
                $next = $this->resolveEffectiveState($moduleKey, null);
                $this->database->table('module_activation_history')->insert([
                    'module_key' => $moduleKey,
                    'scope' => ModuleActivationScope::Team->value,
                    'team_id' => $teamId,
                    'previous_enabled' => $previous->teamEnabled,
                    'new_enabled' => $next->globallyEnabled,
                    'source' => ModuleActivationSource::Manual->value,
                    'actor_user_id' => $actorUserId,
                    'reason' => $reason,
                    'effective_at' => $now,
                    'correlation_id' => request()->headers->get('X-Request-Id'),
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
        $rows = $this->database->table('module_activation_schedules')
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

                $this->database->table('module_activation_schedules')->where('id', $id)->update([
                    'status' => ModuleActivationScheduleStatus::Applied->value,
                    'updated_at' => $now,
                ]);
                $applied++;
            } catch (\Throwable $exception) {
                $this->database->table('module_activation_schedules')->where('id', $id)->update([
                    'status' => ModuleActivationScheduleStatus::Failed->value,
                    'failure_reason' => $exception->getMessage(),
                    'updated_at' => $now,
                ]);
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

        foreach ($this->database->table('teams')->pluck('id') as $id) {
            if (is_numeric($id)) {
                Cache::forget($this->cacheKey($moduleKey, (int) $id));
            }
        }
    }

    private function resolveEffectiveState(string $moduleKey, ?int $teamId): EffectiveModuleState
    {
        $definition = $this->moduleDefinition($moduleKey);
        $deployed = $definition !== null;
        $core = $definition?->category() === ModuleCategory::Core;
        $globalRow = $this->database->table('module_global_states')->where('module_key', $moduleKey)->first();
        $globalValues = is_object($globalRow) ? get_object_vars($globalRow) : [];
        $globalEnabled = $core || (array_key_exists('enabled', $globalValues) ? (bool) $globalValues['enabled'] : false);
        $teamEnabled = $globalEnabled;
        $source = 'global';
        $teamPublicId = null;
        $teamVersion = null;

        if ($teamId !== null) {
            $teamPublicIdValue = $this->database->table('teams')->where('id', $teamId)->value('public_id');
            $teamPublicId = is_string($teamPublicIdValue) ? $teamPublicIdValue : null;
            $teamRow = $this->database->table('module_team_states')->where('module_key', $moduleKey)->where('team_id', $teamId)->first();

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
            technicallyAvailable: $deployed,
            globallyEnabled: $globalEnabled,
            teamEnabled: $teamEnabled,
            effectiveEnabled: $deployed && $globalEnabled && $teamEnabled,
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

        if (! $this->registry->has(new ModuleKey($change->moduleKey))) {
            throw ModuleActivationException::unavailableModuleCannotBeActivated($change->moduleKey);
        }
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
