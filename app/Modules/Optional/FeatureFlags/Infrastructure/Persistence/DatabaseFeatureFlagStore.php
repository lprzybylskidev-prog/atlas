<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Modules\Optional\FeatureFlags\Application\DTOs\FeatureFlagDefinition;
use App\Modules\Optional\FeatureFlags\Application\DTOs\FeatureFlagState;
use App\Modules\Optional\FeatureFlags\Application\Enums\FeatureFlagKey;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseFeatureFlagStore implements FeatureFlagStore
{
    public function __construct(
        private FeatureFlagRegistry $registry,
        private AuditRecorder $audit,
    ) {}

    public function state(FeatureFlagKey|string $key, ?string $teamPublicId = null): FeatureFlagState
    {
        $definition = $this->definition($key);
        $default = ['enabled' => $definition->defaultEnabled];
        $global = $this->globalValue($definition);
        $team = $teamPublicId !== null && $definition->teamScoped ? $this->teamValue($definition, $teamPublicId) : null;

        if ($team !== null) {
            return new FeatureFlagState($definition, $team, 'team', $global, $team, $teamPublicId);
        }

        if ($global !== null) {
            return new FeatureFlagState($definition, $global, 'global', $global, null, $teamPublicId);
        }

        return new FeatureFlagState($definition, $default, 'default', null, null, $teamPublicId);
    }

    public function setGlobal(FeatureFlagKey|string $key, bool $enabled, string $actorPublicId, string $reason): void
    {
        $definition = $this->definition($key);
        $reason = $this->reason($reason);
        $before = $this->globalValue($definition);
        $after = ['enabled' => $enabled];

        DB::transaction(function () use ($definition, $after, $before, $actorPublicId, $reason): void {
            DB::table(DatabaseTable::FEATURE_FLAG_GLOBAL_VALUES)->updateOrInsert(
                ['flag_key' => $definition->key->value],
                [
                    'public_id' => (string) Str::ulid(),
                    'value' => json_encode($after, JSON_THROW_ON_ERROR),
                    'updated_by_public_id' => $actorPublicId,
                    'updated_reason' => $reason,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $this->recordHistory($definition, 'global', null, $before, $after, $actorPublicId, $reason, 'feature_flag.global_updated');
        });
    }

    public function setTeam(FeatureFlagKey|string $key, string $teamPublicId, bool $enabled, string $actorPublicId, string $reason): void
    {
        $definition = $this->definition($key);
        if (! $definition->teamScoped) {
            throw new InvalidArgumentException(sprintf('Feature flag [%s] does not support team overrides.', $definition->key->value));
        }

        $teamId = $this->teamId($teamPublicId);
        $reason = $this->reason($reason);
        $before = $this->teamValue($definition, $teamPublicId);
        $after = ['enabled' => $enabled];

        DB::transaction(function () use ($definition, $teamId, $teamPublicId, $after, $before, $actorPublicId, $reason): void {
            DB::table(DatabaseTable::FEATURE_FLAG_TEAM_VALUES)->updateOrInsert(
                ['flag_key' => $definition->key->value, 'team_id' => $teamId],
                [
                    'public_id' => (string) Str::ulid(),
                    'value' => json_encode($after, JSON_THROW_ON_ERROR),
                    'updated_by_public_id' => $actorPublicId,
                    'updated_reason' => $reason,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $this->recordHistory($definition, 'team', $teamPublicId, $before, $after, $actorPublicId, $reason, 'feature_flag.team_updated');
        });
    }

    public function clearTeam(FeatureFlagKey|string $key, string $teamPublicId, string $actorPublicId, string $reason): void
    {
        $definition = $this->definition($key);
        $teamId = $this->teamId($teamPublicId);
        $reason = $this->reason($reason);
        $before = $this->teamValue($definition, $teamPublicId);

        DB::transaction(function () use ($definition, $teamId, $teamPublicId, $before, $actorPublicId, $reason): void {
            DB::table(DatabaseTable::FEATURE_FLAG_TEAM_VALUES)
                ->where('flag_key', $definition->key->value)
                ->where('team_id', $teamId)
                ->delete();

            $this->recordHistory($definition, 'team', $teamPublicId, $before, null, $actorPublicId, $reason, 'feature_flag.team_cleared');
        });
    }

    public function recentHistory(int $limit = 50): array
    {
        $history = DB::table(DatabaseTable::FEATURE_FLAG_HISTORY)
            ->leftJoin(DatabaseTable::TEAMS, 'feature_flag_history.team_id', '=', 'teams.id')
            ->orderByDesc('feature_flag_history.created_at')
            ->limit($limit)
            ->get([
                'feature_flag_history.public_id',
                'feature_flag_history.flag_key',
                'feature_flag_history.scope',
                'teams.public_id as team_public_id',
                'teams.name as team_name',
                'feature_flag_history.action',
                'feature_flag_history.reason',
                'feature_flag_history.before_value',
                'feature_flag_history.after_value',
                'feature_flag_history.actor_public_id',
                'feature_flag_history.created_at',
            ])
            ->map(fn (object $row): array => [
                'publicId' => $this->scalarString($row->public_id ?? null),
                'flagKey' => $this->scalarString($row->flag_key ?? null),
                'scope' => $this->scalarString($row->scope ?? null),
                'teamPublicId' => is_string($row->team_public_id ?? null) ? $row->team_public_id : null,
                'teamName' => is_string($row->team_name ?? null) ? $row->team_name : null,
                'action' => $this->scalarString($row->action ?? null),
                'reason' => $this->scalarString($row->reason ?? null),
                'before' => $this->decode($row->before_value ?? null),
                'after' => $this->decode($row->after_value ?? null),
                'actorPublicId' => $this->scalarString($row->actor_public_id ?? null),
                'createdAt' => $this->scalarString($row->created_at ?? null),
            ])
            ->all();

        return array_values($history);
    }

    private function definition(FeatureFlagKey|string $key): FeatureFlagDefinition
    {
        $definition = $this->registry->get($key);

        if ($definition === null) {
            $value = $key instanceof FeatureFlagKey ? $key->value : $key;

            throw new InvalidArgumentException(sprintf('Feature flag [%s] is not registered.', $value));
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function globalValue(FeatureFlagDefinition $definition): ?array
    {
        return $this->decode(DB::table(DatabaseTable::FEATURE_FLAG_GLOBAL_VALUES)
            ->where('flag_key', $definition->key->value)
            ->value('value'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamValue(FeatureFlagDefinition $definition, string $teamPublicId): ?array
    {
        return $this->decode(DB::table(DatabaseTable::FEATURE_FLAG_TEAM_VALUES)
            ->join(DatabaseTable::TEAMS, 'feature_flag_team_values.team_id', '=', 'teams.id')
            ->where('feature_flag_team_values.flag_key', $definition->key->value)
            ->where('teams.public_id', $teamPublicId)
            ->value('feature_flag_team_values.value'));
    }

    private function teamId(string $teamPublicId): int
    {
        $teamId = DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        if (! is_int($teamId)) {
            throw new InvalidArgumentException('Team was not found.');
        }

        return $teamId;
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to change feature flags.');
        }

        return $reason;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->stringKeyedArray($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $this->stringKeyedArray($decoded) : null;
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>|null
     */
    private function stringKeyedArray(array $value): ?array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function recordHistory(
        FeatureFlagDefinition $definition,
        string $scope,
        ?string $teamPublicId,
        ?array $before,
        ?array $after,
        string $actorPublicId,
        string $reason,
        string $action,
    ): void {
        $teamId = $teamPublicId === null ? null : $this->teamId($teamPublicId);

        DB::table(DatabaseTable::FEATURE_FLAG_HISTORY)->insert([
            'public_id' => (string) Str::ulid(),
            'flag_key' => $definition->key->value,
            'scope' => $scope,
            'team_id' => $teamId,
            'action' => $action,
            'before_value' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_value' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'actor_public_id' => $actorPublicId,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $this->audit->record(new AuditEvent(
            module: 'feature_flags',
            action: $action,
            result: 'success',
            source: 'admin',
            actorPublicId: $actorPublicId,
            targetType: 'feature_flag',
            targetPublicId: $definition->key->value,
            teamPublicId: $teamPublicId,
            reason: $reason,
            before: $before ?? [],
            after: $after ?? [],
            metadata: ['scope' => $scope],
        ));
    }
}
