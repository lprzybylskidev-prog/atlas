<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Observability\SchedulerHeartbeatMonitor;
use App\Shared\Infrastructure\Operations\OperationalAlertDispatcher;
use App\Shared\Infrastructure\Operations\OperationalAlertMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_alert_dispatcher_sends_configured_channels_once_per_dedupe_window(): void
    {
        Cache::flush();
        Mail::fake();
        Http::fake();
        Config::set('atlas.operations.alerts.enabled', true);
        Config::set('atlas.operations.alerts.email_to', ['ops@example.test']);
        Config::set('atlas.operations.alerts.webhook_url', 'https://alerts.example.test/hook');
        Config::set('atlas.operations.alerts.dedupe_seconds', 900);
        Config::set('atlas.operations.alerts.throttle_seconds', 300);

        $dispatcher = $this->app->make(OperationalAlertDispatcher::class);

        self::assertTrue($dispatcher->send('readiness.failure', 'Readiness failure', 'Blocking checks failed.', 'error'));
        self::assertFalse($dispatcher->send('readiness.failure', 'Readiness failure', 'Blocking checks failed.', 'error'));

        Mail::assertSent(OperationalAlertMail::class, 1);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://alerts.example.test/hook'
            && $request['type'] === 'readiness.failure'
            && $request['severity'] === 'error');
    }

    public function test_operational_alert_command_detects_repeated_failed_jobs(): void
    {
        Cache::flush();
        Http::fake();
        Config::set('atlas.operations.alerts.enabled', true);
        Config::set('atlas.operations.alerts.email_to', []);
        Config::set('atlas.operations.alerts.webhook_url', 'https://alerts.example.test/hook');
        Config::set('atlas.operations.alerts.failed_jobs_threshold', 2);
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');
        Config::set('session.driver', 'array');
        Config::set('scout.meilisearch.host', '');
        Config::set('atlas.operations.health.meilisearch_critical', false);
        Config::set('atlas.operations.health.clamav.critical', false);
        Config::set('atlas.operations.health.clamav.host', null);
        Config::set('atlas.operations.health.chromium.critical', false);
        Config::set('atlas.operations.health.chromium.binary', null);
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(10);

        $this->insertFailedJob('55555555-5555-4555-8555-555555555555');
        $this->insertFailedJob('66666666-6666-4666-8666-666666666666');

        self::assertSame(0, Artisan::call('system:operational-alerts'));

        Http::assertSent(function (Request $request): bool {
            $context = $request['context'];

            return $request->url() === 'https://alerts.example.test/hook'
                && $request['type'] === 'queue.failed_jobs.repeated'
                && is_array($context)
                && ($context['failed_jobs'] ?? null) === 2;
        });
    }

    public function test_operational_alert_command_ignores_handled_failed_jobs(): void
    {
        Cache::flush();
        Http::fake();
        Config::set('atlas.operations.alerts.enabled', true);
        Config::set('atlas.operations.alerts.email_to', []);
        Config::set('atlas.operations.alerts.webhook_url', 'https://alerts.example.test/hook');
        Config::set('atlas.operations.alerts.failed_jobs_threshold', 2);
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');
        Config::set('session.driver', 'array');
        Config::set('scout.meilisearch.host', '');
        Config::set('atlas.operations.health.meilisearch_critical', false);
        Config::set('atlas.operations.health.clamav.critical', false);
        Config::set('atlas.operations.health.clamav.host', null);
        Config::set('atlas.operations.health.chromium.critical', false);
        Config::set('atlas.operations.health.chromium.binary', null);
        $this->app->make(SchedulerHeartbeatMonitor::class)->markHealthy(10);

        $this->insertFailedJob('77777777-7777-4777-8777-777777777777');
        $this->insertFailedJob('88888888-8888-4888-8888-888888888888');
        DB::table(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS)->insert([
            'public_id' => (string) Str::ulid(),
            'failed_job_uuid' => '88888888-8888-4888-8888-888888888888',
            'acknowledged_by_user_id' => null,
            'reason' => null,
            'acknowledged_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(0, Artisan::call('system:operational-alerts'));

        Http::assertNotSent(fn (Request $request): bool => $request['type'] === 'queue.failed_jobs.repeated');
    }

    private function insertFailedJob(string $uuid): void
    {
        DB::table(DatabaseTable::FAILED_JOBS)->insert([
            'uuid' => $uuid,
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: Demo failure',
            'failed_at' => now(),
        ]);
    }
}
