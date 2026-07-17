<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Admin;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class ImpersonationSimulationStore
{
    public function __construct(
        private CacheRepository $cache,
    ) {}

    public function put(string $impersonationSessionId, string $key, mixed $value): void
    {
        $this->cache->put($this->key($impersonationSessionId, $key), $value, 14400);
    }

    public function get(string $impersonationSessionId, string $key): mixed
    {
        return $this->cache->get($this->key($impersonationSessionId, $key));
    }

    public function deleteSession(string $impersonationSessionId): void
    {
        $indexKey = $this->indexKey($impersonationSessionId);

        $index = $this->cache->get($indexKey, []);

        if (! is_array($index)) {
            $index = [];
        }

        foreach ($index as $key) {
            if (is_string($key)) {
                $this->cache->forget($key);
            }
        }

        $this->cache->forget($indexKey);
    }

    private function key(string $impersonationSessionId, string $key): string
    {
        $cacheKey = sprintf('atlas:impersonation:%s:simulation:%s', $impersonationSessionId, $key);
        $indexKey = $this->indexKey($impersonationSessionId);
        $index = $this->cache->get($indexKey, []);

        if (is_array($index) && ! in_array($cacheKey, $index, true)) {
            $index[] = $cacheKey;
            $this->cache->put($indexKey, $index, 14400);
        }

        return $cacheKey;
    }

    private function indexKey(string $impersonationSessionId): string
    {
        return sprintf('atlas:impersonation:%s:simulation:index', $impersonationSessionId);
    }
}
