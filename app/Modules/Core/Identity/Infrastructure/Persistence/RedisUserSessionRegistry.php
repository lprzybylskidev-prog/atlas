<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Sessions\UserSessionMetadata;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class RedisUserSessionRegistry implements UserSessionRegistry
{
    private const SESSION_PREFIX = 'atlas:sessions:';

    private const USER_PREFIX = 'atlas:user_sessions:';

    private const TEAM_PREFIX = 'atlas:team_sessions:';

    private const ONLINE_WINDOW_SECONDS = 300;

    public function touch(Request $request): void
    {
        $user = $request->user();

        if (! $request->hasSession() || ! is_object($user)) {
            return;
        }

        $session = $request->session();
        $sessionId = $session->getId();
        $userPublicId = data_get($user, 'public_id');
        $userId = data_get($user, 'id');

        if ($sessionId === '' || ! is_string($userPublicId) || ! is_numeric($userId)) {
            return;
        }

        $createdAt = $this->sessionTimestamp($session, 'atlas_session_created_at');
        $previousTeamPublicId = $this->nullableString($this->command('hget', [$this->sessionKey($sessionId), 'active_team_public_id']));
        $activeTeamPublicId = $session->get('active_team_public_id');
        $activeTeamPublicId = is_string($activeTeamPublicId) && $activeTeamPublicId !== '' ? $activeTeamPublicId : null;
        $activeTeamName = $activeTeamPublicId === null ? null : $this->teamName($activeTeamPublicId);
        $now = Carbon::now();

        $metadata = [
            'session_id' => $sessionId,
            'user_id' => (string) (int) $userId,
            'user_public_id' => $userPublicId,
            'user_name' => $this->scalarString(data_get($user, 'name', '')),
            'user_email' => $this->scalarString(data_get($user, 'email', '')),
            'created_at' => $createdAt->toIso8601String(),
            'last_activity_at' => $now->toIso8601String(),
            'ip_address' => $request->ip() ?? '',
            'ip_location' => $this->approximateIpLocation($request->ip()),
            'user_agent' => $request->userAgent() ?? '',
            'browser' => $this->browser($request->userAgent()),
            'device' => $this->device($request->userAgent()),
            'active_team_public_id' => $activeTeamPublicId ?? '',
            'active_team_name' => $activeTeamName ?? '',
        ];

        $this->command('hmset', [$this->sessionKey($sessionId), $metadata]);
        $this->command('expire', [$this->sessionKey($sessionId), $this->metadataTtlSeconds()]);
        $this->command('sadd', [$this->userKey($userPublicId), $sessionId]);
        $this->command('expire', [$this->userKey($userPublicId), $this->metadataTtlSeconds()]);

        if ($previousTeamPublicId !== null && $previousTeamPublicId !== $activeTeamPublicId) {
            $this->command('srem', [$this->teamKey($previousTeamPublicId), $sessionId]);
        }

        if ($activeTeamPublicId !== null) {
            $this->command('sadd', [$this->teamKey($activeTeamPublicId), $sessionId]);
            $this->command('expire', [$this->teamKey($activeTeamPublicId), $this->metadataTtlSeconds()]);
        }
    }

    public function activeForUser(string $userPublicId): array
    {
        $sessions = [];

        foreach ($this->sessionIdsForUser($userPublicId) as $sessionId) {
            $metadata = $this->metadata($sessionId);

            if ($metadata === null) {
                $this->command('srem', [$this->userKey($userPublicId), $sessionId]);

                continue;
            }

            $sessions[] = $metadata;
        }

        usort($sessions, static fn (UserSessionMetadata $a, UserSessionMetadata $b): int => strcmp($b->lastActivityAt, $a->lastActivityAt));

        return $sessions;
    }

    public function onlineUserPublicIds(): array
    {
        $publicIds = [];
        $threshold = Carbon::now()->subSeconds(self::ONLINE_WINDOW_SECONDS);

        foreach ($this->stringList($this->command('keys', [self::SESSION_PREFIX.'*'])) as $key) {
            $sessionId = Str::after((string) $key, self::SESSION_PREFIX);
            $metadata = $this->metadata($sessionId);

            if ($metadata === null || Carbon::parse($metadata->lastActivityAt)->lt($threshold)) {
                continue;
            }

            $publicIds[] = $metadata->userPublicId;
        }

        return array_values(array_unique($publicIds));
    }

    public function terminate(string $sessionId): void
    {
        $metadata = $this->metadata($sessionId);

        if ($metadata !== null) {
            $this->command('srem', [$this->userKey($metadata->userPublicId), $sessionId]);

            if ($metadata->activeTeamPublicId !== null) {
                $this->command('srem', [$this->teamKey($metadata->activeTeamPublicId), $sessionId]);
            }
        }

        app('session')->getHandler()->destroy($sessionId);
        $this->command('del', [$this->sessionKey($sessionId)]);
    }

    public function terminateOtherSessions(string $userPublicId, string $currentSessionId): void
    {
        foreach ($this->sessionIdsForUser($userPublicId) as $sessionId) {
            if (hash_equals($currentSessionId, $sessionId)) {
                continue;
            }

            $this->terminate($sessionId);
        }
    }

    public function invalidateUser(string $userPublicId): void
    {
        foreach ($this->sessionIdsForUser($userPublicId) as $sessionId) {
            $this->terminate($sessionId);
        }
    }

    public function invalidateUserTeam(string $userPublicId, string $teamPublicId): void
    {
        foreach ($this->sessionIdsForUser($userPublicId) as $sessionId) {
            $metadata = $this->metadata($sessionId);

            if ($metadata?->activeTeamPublicId === $teamPublicId) {
                $this->terminate($sessionId);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function sessionIdsForUser(string $userPublicId): array
    {
        return array_values(array_filter(
            $this->stringList($this->command('smembers', [$this->userKey($userPublicId)])),
            static fn (string $value): bool => $value !== '',
        ));
    }

    private function metadata(string $sessionId): ?UserSessionMetadata
    {
        $data = $this->command('hgetall', [$this->sessionKey($sessionId)]);

        if (! is_array($data) || $data === []) {
            return null;
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[$this->scalarString($key)] = $this->scalarString($value);
        }

        return new UserSessionMetadata(
            sessionId: $normalized['session_id'] ?? $sessionId,
            userId: (int) ($normalized['user_id'] ?? 0),
            userPublicId: $normalized['user_public_id'] ?? '',
            userName: $normalized['user_name'] ?? '',
            userEmail: $normalized['user_email'] ?? '',
            createdAt: $normalized['created_at'] ?? '',
            lastActivityAt: $normalized['last_activity_at'] ?? '',
            ipAddress: $normalized['ip_address'] ?? '',
            ipLocation: $normalized['ip_location'] ?? '',
            userAgent: $normalized['user_agent'] ?? '',
            browser: $normalized['browser'] ?? 'Unknown browser',
            device: $normalized['device'] ?? 'Unknown device',
            activeTeamPublicId: ($normalized['active_team_public_id'] ?? '') !== '' ? $normalized['active_team_public_id'] : null,
            activeTeamName: ($normalized['active_team_name'] ?? '') !== '' ? $normalized['active_team_name'] : null,
        );
    }

    private function sessionTimestamp(Session $session, string $key): Carbon
    {
        $value = $session->get($key);

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        $now = Carbon::now();
        $session->put($key, $now->toIso8601String());

        return $now;
    }

    private function teamName(string $teamPublicId): ?string
    {
        $name = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('name');

        return is_string($name) ? $name : null;
    }

    private function approximateIpLocation(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'Unknown';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local network';
        }

        return 'External network';
    }

    private function browser(?string $userAgent): string
    {
        $agent = strtolower($userAgent ?? '');

        return match (true) {
            str_contains($agent, 'edg/') => 'Edge',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'chrome/') => 'Chrome',
            str_contains($agent, 'safari/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    private function device(?string $userAgent): string
    {
        $agent = strtolower($userAgent ?? '');

        return match (true) {
            str_contains($agent, 'mobile') => 'Mobile',
            str_contains($agent, 'tablet') || str_contains($agent, 'ipad') => 'Tablet',
            $agent !== '' => 'Desktop',
            default => 'Unknown device',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $value): string => $this->scalarString($value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    private function metadataTtlSeconds(): int
    {
        $configured = config('atlas.security.sessions.max_lifetime_minutes', 720);
        $maximum = is_numeric($configured) ? (int) $configured : 720;

        return max(3600, ($maximum + 60) * 60);
    }

    private function sessionKey(string $sessionId): string
    {
        return self::SESSION_PREFIX.$sessionId;
    }

    private function userKey(string $userPublicId): string
    {
        return self::USER_PREFIX.$userPublicId;
    }

    private function teamKey(string $teamPublicId): string
    {
        return self::TEAM_PREFIX.$teamPublicId;
    }

    /**
     * @param  list<mixed>  $parameters
     */
    private function command(string $command, array $parameters): mixed
    {
        return $this->redis()->command($command, $parameters);
    }

    private function redis(): Connection
    {
        return Redis::connection($this->connectionName());
    }

    private function connectionName(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
