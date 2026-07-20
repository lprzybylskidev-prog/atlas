<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Infrastructure\Persistence;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Settings\Application\Contracts\SettingsStore;
use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use App\Modules\Core\Settings\Application\Settings\SettingsDefaults;
use App\Modules\Core\Settings\Application\Settings\SettingValueValidator;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use JsonException;

final readonly class DatabaseSettingsStore implements SettingsStore
{
    public function __construct(
        private ConnectionInterface $db,
        private CacheRepository $cache,
        private SettingsDefaults $defaults,
        private SettingValueValidator $validator,
        private AuditRecorder $audit,
    ) {}

    public function getGlobal(GlobalSettingKey $key): mixed
    {
        return $this->remember(
            sprintf('atlas.settings.global.%s', $key->value),
            fn (): mixed => $this->settingValue(DatabaseTable::SETTINGS_GLOBAL_VALUES, ['key' => $key->value], $this->defaults->global($key)),
        );
    }

    public function putGlobal(GlobalSettingKey $key, mixed $value): void
    {
        $this->upsert(DatabaseTable::SETTINGS_GLOBAL_VALUES, ['key' => $key->value], $this->validator->validate($key, $value));
        $this->cache->forget(sprintf('atlas.settings.global.%s', $key->value));
    }

    public function getTeam(int $teamId, TeamSettingKey $key): mixed
    {
        return $this->remember(
            sprintf('atlas.settings.team.%d.%s', $teamId, $key->value),
            fn (): mixed => $this->settingValue(DatabaseTable::SETTINGS_TEAM_VALUES, ['team_id' => $teamId, 'key' => $key->value], $this->defaults->team($key)),
        );
    }

    public function putTeam(int $teamId, TeamSettingKey $key, mixed $value): void
    {
        $this->upsert(DatabaseTable::SETTINGS_TEAM_VALUES, ['team_id' => $teamId, 'key' => $key->value], $this->validator->validate($key, $value));
        $this->cache->forget(sprintf('atlas.settings.team.%d.%s', $teamId, $key->value));
    }

    public function getUser(int $userId, UserSettingKey $key): mixed
    {
        return $this->remember(
            sprintf('atlas.settings.user.%d.%s', $userId, $key->value),
            fn (): mixed => $this->settingValue(DatabaseTable::SETTINGS_USER_VALUES, ['user_id' => $userId, 'key' => $key->value], $this->defaults->user($key)),
        );
    }

    public function putUser(int $userId, UserSettingKey $key, mixed $value): void
    {
        $this->upsert(DatabaseTable::SETTINGS_USER_VALUES, ['user_id' => $userId, 'key' => $key->value], $this->validator->validate($key, $value));
        $this->cache->forget(sprintf('atlas.settings.user.%d.%s', $userId, $key->value));
    }

    public function getSecurity(SecuritySettingKey $key): mixed
    {
        return $this->remember(
            sprintf('atlas.settings.security.%s', $key->value),
            fn (): mixed => $this->settingValue(DatabaseTable::SETTINGS_SECURITY_VALUES, ['key' => $key->value], $this->defaults->security($key)),
        );
    }

    public function putSecurity(SecuritySettingKey $key, mixed $value, ?string $actorPublicId = null, ?string $reason = null): void
    {
        $before = $this->getSecurity($key);
        $validated = $this->validator->validate($key, $value);

        $this->upsert(DatabaseTable::SETTINGS_SECURITY_VALUES, ['key' => $key->value], $validated);
        $this->cache->forget(sprintf('atlas.settings.security.%s', $key->value));

        $this->audit->record(new AuditEvent(
            module: 'settings',
            action: 'settings.security.updated',
            result: 'succeeded',
            source: 'settings',
            actorPublicId: $actorPublicId,
            targetType: 'security_setting',
            targetPublicId: $key->value,
            reason: $reason,
            before: [$key->value => $before],
            after: [$key->value => $validated],
            security: true,
            securityCategory: SecurityAuditCategory::Settings,
        ));
    }

    /**
     * @param  array<string, int|string>  $identity
     */
    private function settingValue(string $table, array $identity, mixed $default): mixed
    {
        $query = $this->db->table($table);

        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $stored = $query->value('value');

        if (! is_string($stored)) {
            return $default;
        }

        try {
            return json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }
    }

    /**
     * @param  array<string, int|string>  $identity
     */
    private function upsert(string $table, array $identity, mixed $value): void
    {
        $now = Carbon::now();

        $this->db->table($table)->updateOrInsert($identity, [
            'value' => json_encode($value, JSON_THROW_ON_ERROR),
            'updated_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function remember(string $key, Closure $resolver): mixed
    {
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $value = $resolver();

        $this->cache->put($key, $value, 300);

        return $value;
    }
}
