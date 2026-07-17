<?php

declare(strict_types=1);

namespace App\Modules\Core\Health\Infrastructure\Readiness;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Health\Application\Readiness\ReadinessCheckResult;
use App\Modules\Core\Health\Application\Readiness\ReadinessReport;
use App\Shared\Application\Modules\ModuleKey;
use App\Shared\Application\Modules\ModuleRegistry;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Meilisearch\Client as MeilisearchClient;
use Throwable;

final readonly class AtlasReadinessChecker implements ReadinessChecker
{
    public function __construct(
        private ConnectionInterface $database,
        private RedisFactory $redis,
        private Filesystem $files,
        private SchedulerHeartbeatMonitor $schedulerHeartbeat,
        private ModuleRegistry $modules,
    ) {}

    public function check(): ReadinessReport
    {
        return new ReadinessReport([
            $this->criticalConfiguration(),
            $this->postgresql(),
            $this->redis(),
            $this->queues(),
            $this->storage(),
            $this->scheduler(),
            $this->meilisearch(),
            $this->clamav(),
            $this->chromiumPdf(),
        ], CarbonImmutable::now('UTC'));
    }

    private function criticalConfiguration(): ReadinessCheckResult
    {
        $required = [
            'app.name',
            'app.env',
            'app.url',
            'app.timezone',
            'database.default',
            'cache.default',
            'queue.default',
            'session.driver',
            'atlas.release.version',
            'atlas.release.id',
        ];

        $missing = [];

        foreach ($required as $key) {
            $value = Config::get($key);

            if (! is_string($value) || trim($value) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            return ReadinessCheckResult::unhealthy(
                key: 'critical-configuration',
                label: 'Critical configuration',
                blocking: true,
                description: 'One or more critical configuration values are missing.',
                metadata: ['missing_count' => count($missing)],
            );
        }

        if (Config::string('app.timezone') !== 'Europe/Warsaw' || Config::string('database.default') !== 'pgsql') {
            return ReadinessCheckResult::unhealthy(
                key: 'critical-configuration',
                label: 'Critical configuration',
                blocking: true,
                description: 'Critical configuration does not match the Atlas runtime contract.',
            );
        }

        return ReadinessCheckResult::healthy(
            key: 'critical-configuration',
            label: 'Critical configuration',
            blocking: true,
            description: 'Critical configuration is present.',
        );
    }

    private function postgresql(): ReadinessCheckResult
    {
        try {
            $this->database->select('select 1');

            return ReadinessCheckResult::healthy(
                key: 'postgresql',
                label: 'PostgreSQL',
                blocking: true,
                description: 'PostgreSQL accepted a lightweight readiness query.',
            );
        } catch (Throwable) {
            return ReadinessCheckResult::unhealthy(
                key: 'postgresql',
                label: 'PostgreSQL',
                blocking: true,
                description: 'PostgreSQL is not accepting readiness queries.',
            );
        }
    }

    private function redis(): ReadinessCheckResult
    {
        $usesRedis = in_array(Config::string('cache.default'), ['redis'], true)
            || in_array(Config::string('queue.default'), ['redis'], true)
            || in_array(Config::string('session.driver'), ['redis'], true);

        if (! $usesRedis) {
            return ReadinessCheckResult::healthy(
                key: 'redis',
                label: 'Redis',
                blocking: false,
                description: 'Redis is not required by the active cache, queue, or session configuration.',
            );
        }

        try {
            $this->redis->connection()->ping();

            return ReadinessCheckResult::healthy(
                key: 'redis',
                label: 'Redis',
                blocking: true,
                description: 'Redis accepted a lightweight readiness ping.',
            );
        } catch (Throwable) {
            return ReadinessCheckResult::unhealthy(
                key: 'redis',
                label: 'Redis',
                blocking: true,
                description: 'Redis is required but did not accept a readiness ping.',
            );
        }
    }

    private function queues(): ReadinessCheckResult
    {
        $connection = Config::string('queue.default');

        if ($connection === 'redis') {
            try {
                $this->redis->connection()->ping();

                return ReadinessCheckResult::healthy(
                    key: 'queues',
                    label: 'Queues',
                    blocking: true,
                    description: 'The configured Redis queue backend is reachable.',
                    metadata: ['connection' => $connection],
                );
            } catch (Throwable) {
                return ReadinessCheckResult::unhealthy(
                    key: 'queues',
                    label: 'Queues',
                    blocking: true,
                    description: 'The configured Redis queue backend is not reachable.',
                    metadata: ['connection' => $connection],
                );
            }
        }

        return ReadinessCheckResult::healthy(
            key: 'queues',
            label: 'Queues',
            blocking: true,
            description: 'Queue configuration is present.',
            metadata: ['connection' => $connection],
        );
    }

    private function storage(): ReadinessCheckResult
    {
        $path = storage_path('app');

        if ($this->files->isDirectory($path) && $this->files->isWritable($path)) {
            return ReadinessCheckResult::healthy(
                key: 'storage',
                label: 'Storage',
                blocking: true,
                description: 'Application storage is writable.',
            );
        }

        return ReadinessCheckResult::unhealthy(
            key: 'storage',
            label: 'Storage',
            blocking: true,
            description: 'Application storage is not writable.',
        );
    }

    private function scheduler(): ReadinessCheckResult
    {
        $status = $this->schedulerHeartbeat->status();
        $isFresh = ($status['isFresh'] ?? false) === true;

        if (($status['status'] ?? null) === 'healthy' && $isFresh) {
            return ReadinessCheckResult::healthy(
                key: 'scheduler',
                label: 'Scheduler',
                blocking: true,
                description: 'Scheduler heartbeat is fresh.',
                metadata: [
                    'last_success_at' => $this->scalarString($status['lastSuccessAt'] ?? null),
                    'stale_after_seconds' => $this->scalarInt($status['staleAfterSeconds'] ?? null),
                ],
            );
        }

        return ReadinessCheckResult::unhealthy(
            key: 'scheduler',
            label: 'Scheduler',
            blocking: true,
            description: 'Scheduler heartbeat is missing, stale, or failed.',
            metadata: [
                'status' => $this->scalarString($status['status'] ?? null),
                'last_success_at' => $this->scalarString($status['lastSuccessAt'] ?? null),
                'stale_after_seconds' => $this->scalarInt($status['staleAfterSeconds'] ?? null),
            ],
        );
    }

    private function meilisearch(): ReadinessCheckResult
    {
        $critical = Config::boolean('atlas.operations.health.meilisearch_critical', false);
        $host = Config::string('scout.meilisearch.host', '');

        if (trim($host) === '') {
            return $critical
                ? ReadinessCheckResult::unhealthy('meilisearch', 'Meilisearch', true, 'Meilisearch is critical but no host is configured.')
                : ReadinessCheckResult::degraded('meilisearch', 'Meilisearch', false, 'Meilisearch is not configured and is treated as degraded.');
        }

        try {
            $key = Config::get('scout.meilisearch.key');
            $client = new MeilisearchClient($host, is_string($key) && $key !== '' ? $key : null);
            $client->health();

            return ReadinessCheckResult::healthy(
                key: 'meilisearch',
                label: 'Meilisearch',
                blocking: $critical,
                description: $critical
                    ? 'Critical Meilisearch dependency is reachable.'
                    : 'Optional Meilisearch dependency is reachable.',
            );
        } catch (Throwable) {
            return $critical
                ? ReadinessCheckResult::unhealthy('meilisearch', 'Meilisearch', true, 'Critical Meilisearch dependency is not reachable.')
                : ReadinessCheckResult::degraded('meilisearch', 'Meilisearch', false, 'Optional Meilisearch dependency is not reachable.');
        }
    }

    private function clamav(): ReadinessCheckResult
    {
        $critical = Config::boolean('atlas.operations.health.clamav.critical', false)
            || (app()->environment('production') && $this->modules->has(new ModuleKey('files')));
        $host = $this->nullableConfigString('atlas.operations.health.clamav.host');
        $port = Config::integer('atlas.operations.health.clamav.port', 3310);

        if ($host === null) {
            return $critical
                ? ReadinessCheckResult::unhealthy('clamav', 'ClamAV', true, 'ClamAV is critical but no daemon host is configured.')
                : ReadinessCheckResult::healthy('clamav', 'ClamAV', false, 'ClamAV is not required by an active production Files capability.');
        }

        if ($this->canOpenTcpConnection($host, $port)) {
            return ReadinessCheckResult::healthy(
                key: 'clamav',
                label: 'ClamAV',
                blocking: $critical,
                description: $critical
                    ? 'Critical ClamAV daemon is reachable.'
                    : 'Optional ClamAV daemon is reachable.',
                metadata: ['port' => $port],
            );
        }

        return $critical
            ? ReadinessCheckResult::unhealthy('clamav', 'ClamAV', true, 'Critical ClamAV daemon is not reachable.', ['port' => $port])
            : ReadinessCheckResult::degraded('clamav', 'ClamAV', false, 'Optional ClamAV daemon is not reachable.', ['port' => $port]);
    }

    private function chromiumPdf(): ReadinessCheckResult
    {
        $critical = Config::boolean('atlas.operations.health.chromium.critical', false);
        $binary = $this->nullableConfigString('atlas.operations.health.chromium.binary');

        if ($binary === null) {
            return $critical
                ? ReadinessCheckResult::unhealthy('chromium-pdf', 'Chromium/PDF', true, 'Chromium/PDF rendering is critical but no binary is configured.')
                : ReadinessCheckResult::degraded('chromium-pdf', 'Chromium/PDF', false, 'Chromium/PDF renderer is not configured and is treated as degraded.');
        }

        if ($this->files->isFile($binary) && is_executable($binary)) {
            return ReadinessCheckResult::healthy(
                key: 'chromium-pdf',
                label: 'Chromium/PDF',
                blocking: $critical,
                description: $critical
                    ? 'Critical Chromium/PDF renderer binary is executable.'
                    : 'Optional Chromium/PDF renderer binary is executable.',
            );
        }

        return $critical
            ? ReadinessCheckResult::unhealthy('chromium-pdf', 'Chromium/PDF', true, 'Critical Chromium/PDF renderer binary is missing or not executable.')
            : ReadinessCheckResult::degraded('chromium-pdf', 'Chromium/PDF', false, 'Optional Chromium/PDF renderer binary is missing or not executable.');
    }

    private function canOpenTcpConnection(string $host, int $port): bool
    {
        if ($port < 1 || $port > 65_535) {
            return false;
        }

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorCode,
            $errorMessage,
            1.0,
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function nullableConfigString(string $key): ?string
    {
        $value = Config::get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function scalarString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function scalarInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
