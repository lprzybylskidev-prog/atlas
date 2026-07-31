<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation;

use App\Shared\Infrastructure\Observability\ApplicationLogReader;
use App\Shared\Infrastructure\Observability\ConfigureAtlasLogging;
use App\Shared\Infrastructure\Observability\ObservabilityContext;
use App\Shared\Infrastructure\Observability\RedactingLogProcessor;
use App\Shared\Infrastructure\Observability\SanitizedSentryEventProcessor;
use App\Shared\Infrastructure\Observability\SensitiveDataRedactor;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Sentry\Event as SentryEvent;
use Sentry\UserDataBag;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

final class ObservabilityFoundationTest extends TestCase
{
    public function test_sensitive_data_redactor_removes_secrets_headers_bodies_and_personal_data(): void
    {
        $redacted = (new SensitiveDataRedactor)->redactArray([
            'password' => 'secret',
            'api_key' => 'key',
            'headers' => ['Authorization' => 'Bearer secret'],
            'request_body' => ['email' => 'user@example.test'],
            'safe' => 'value',
            'nested' => [
                'session_id' => 'session-secret',
                'amount_minor' => 100_00,
            ],
        ]);

        self::assertSame('[redacted]', $redacted['password']);
        self::assertSame('[redacted]', $redacted['api_key']);
        self::assertSame('[redacted]', $redacted['headers']);
        self::assertSame('[redacted]', $redacted['request_body']);
        self::assertSame('value', $redacted['safe']);

        $nested = $redacted['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('[redacted]', $nested['session_id']);
        self::assertSame('[redacted]', $nested['amount_minor']);
    }

    public function test_log_processor_redacts_sensitive_context_and_extra(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'testing',
            level: Level::Info,
            message: 'probe',
            context: [
                'password' => 'secret',
                'correlation_id' => 'request-1',
            ],
            extra: [
                'cookie' => 'session=secret',
                'source' => 'http',
            ],
        );

        $processed = (new RedactingLogProcessor)($record);

        self::assertSame('[redacted]', $processed->context['password']);
        self::assertSame('request-1', $processed->context['correlation_id']);
        self::assertSame('[redacted]', $processed->extra['cookie']);
        self::assertSame('http', $processed->extra['source']);
    }

    public function test_application_log_reader_returns_curated_sanitized_rows_only(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atlas-log-');
        self::assertIsString($path);

        try {
            file_put_contents($path, implode(PHP_EOL, [
                json_encode([
                    'message' => 'Login failed for user@example.test',
                    'context' => [
                        'correlation_id' => 'request-1',
                        'module' => 'identity',
                        'source' => 'http',
                        'event_name' => 'auth.login.failed',
                        'email' => 'user@example.test',
                    ],
                    'level_name' => 'WARNING',
                    'channel' => 'local',
                    'datetime' => '2026-07-17T12:00:00+00:00',
                    'extra' => ['request_id' => 'request-1'],
                ], JSON_THROW_ON_ERROR),
                '[2026-07-17 12:01:00] local.ERROR: Token leaked Bearer secret-token',
            ]).PHP_EOL);

            $result = (new ApplicationLogReader(new SensitiveDataRedactor, $path))->latest();
            $files = (new ApplicationLogReader(new SensitiveDataRedactor, $path))->logFiles();

            self::assertSame(2, $result['summary']['rows']);
            self::assertSame(basename($path), $result['summary']['pathLabel']);
            self::assertSame(basename($path), $files[0]['name']);
            self::assertCount(2, $result['entries']);

            self::assertSame('error', $result['entries'][0]['level']);
            self::assertSame('Token leaked [redacted]', $result['entries'][0]['message']);
            self::assertSame('warning', $result['entries'][1]['level']);
            self::assertSame('identity', $result['entries'][1]['module']);
            self::assertSame('http', $result['entries'][1]['source']);
            self::assertSame('auth.login.failed', $result['entries'][1]['eventName']);
            self::assertSame('request-1', $result['entries'][1]['correlationId']);
            self::assertSame('request-1', $result['entries'][1]['requestId']);
            self::assertSame('Login failed for [redacted]', $result['entries'][1]['message']);
        } finally {
            @unlink($path);
        }
    }

    public function test_application_log_reader_groups_multiline_text_stack_traces(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atlas-log-');
        self::assertIsString($path);

        try {
            file_put_contents($path, implode(PHP_EOL, [
                '[2026-07-17 12:01:00] local.ERROR: Failure for user@example.test',
                '[stacktrace]',
                '#0 /workspace/app/Example.php(1): probe()',
                '#1 {main}',
                '[2026-07-17 12:02:00] local.INFO: Recovered',
            ]).PHP_EOL);

            $result = (new ApplicationLogReader(new SensitiveDataRedactor, $path))->latest();

            self::assertSame(2, $result['summary']['rows']);
            self::assertCount(2, $result['entries']);
            self::assertSame('info', $result['entries'][0]['level']);
            self::assertSame('Recovered', $result['entries'][0]['message']);
            self::assertSame('error', $result['entries'][1]['level']);
            self::assertSame('Failure for [redacted]', $result['entries'][1]['message']);
            self::assertStringContainsString('[stacktrace]', $result['entries'][1]['details']);
            self::assertStringContainsString('#1 {main}', $result['entries'][1]['details']);
        } finally {
            @unlink($path);
        }
    }

    public function test_application_log_reader_splits_inline_context_from_text_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atlas-log-');
        self::assertIsString($path);

        try {
            file_put_contents($path, '[2026-07-17 12:03:00] local.ERROR: Route [login] not defined. {"request_id":"request-5","exception":"[object]"}'.PHP_EOL);

            $result = (new ApplicationLogReader(new SensitiveDataRedactor, $path))->latest();

            self::assertSame('Route [login] not defined.', $result['entries'][0]['message']);
            self::assertSame('{"request_id":"request-5","exception":"[object]"}', $result['entries'][0]['details']);
        } finally {
            @unlink($path);
        }
    }

    public function test_production_logging_tap_configures_json_formatter(): void
    {
        $this->app['env'] = 'production';
        $handler = new TestHandler;
        $logger = new Logger('testing', [$handler]);

        (new ConfigureAtlasLogging)($logger);

        self::assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }

    public function test_sentry_event_processor_sanitizes_payload_and_keeps_public_user_identifier_only(): void
    {
        $event = SentryEvent::createEvent();
        $event->setRequest([
            'headers' => ['Cookie' => 'atlas_session=secret'],
            'data' => ['password' => 'secret'],
            'url' => 'https://atlas.test/admin',
        ]);
        $event->setContext('atlas', [
            'correlation_id' => 'request-1',
            'email' => 'user@example.test',
        ]);
        $event->setExtra([
            'request_body' => ['token' => 'secret'],
            'module' => 'identity',
        ]);
        $event->setUser(new UserDataBag('01TESTUSER', 'user@example.test', '127.0.0.1', 'admin'));

        $processed = SanitizedSentryEventProcessor::handle($event);

        self::assertSame('[redacted]', $processed->getRequest()['headers']);
        $requestData = $processed->getRequest()['data'] ?? null;
        self::assertIsArray($requestData);
        self::assertSame('[redacted]', $requestData['password']);
        self::assertSame('https://atlas.test/admin', $processed->getRequest()['url']);
        self::assertSame('request-1', $processed->getContexts()['atlas']['correlation_id']);
        self::assertSame('[redacted]', $processed->getContexts()['atlas']['email']);
        self::assertSame('[redacted]', $processed->getExtra()['request_body']);
        self::assertSame('identity', $processed->getExtra()['module']);
        $user = $processed->getUser();
        self::assertInstanceOf(UserDataBag::class, $user);
        self::assertSame('01TESTUSER', $user->getId());
        self::assertNull($user->getEmail());
        self::assertNull($user->getIpAddress());
        self::assertNull($user->getUsername());
    }

    public function test_observability_context_applies_stable_runtime_metadata(): void
    {
        Context::flush();

        $context = (new ObservabilityContext)->apply(
            source: 'queue',
            eventName: 'queue.job',
            module: 'notifications',
            correlationId: 'request-1',
            causationId: 'event-1',
            extra: ['job' => 'App\\Modules\\Core\\Notifications\\Presentation\\Jobs\\DeliverNotification'],
        );

        self::assertSame('request-1', $context['correlation_id']);
        self::assertSame('event-1', $context['causation_id']);
        self::assertSame('queue', $context['source']);
        self::assertSame('queue.job', $context['event_name']);
        self::assertSame('notifications', $context['module']);
        self::assertSame('App\\Modules\\Core\\Notifications\\Presentation\\Jobs\\DeliverNotification', $context['job']);
        self::assertSame(config()->string('app.env'), $context['environment']);
        self::assertSame(config()->string('atlas.release.version'), $context['release_version']);
        self::assertSame(config()->string('atlas.release.id'), $context['release_id']);
        self::assertSame('request-1', Context::get('correlation_id'));
    }

    public function test_observability_context_preserves_existing_correlation_actor_and_team_context(): void
    {
        Context::flush();
        Context::add([
            'correlation_id' => 'request-2',
            'actor_public_id' => '01ACTOR',
            'team_public_id' => '01TEAM',
        ]);

        $context = (new ObservabilityContext)->apply(
            source: 'cli',
            eventName: 'cli.command',
            module: 'shared',
        );

        self::assertSame('request-2', $context['correlation_id']);
        self::assertSame('01ACTOR', $context['actor_public_id']);
        self::assertSame('01TEAM', $context['team_public_id']);
    }

    public function test_observability_context_maps_known_modules_from_classes_and_commands(): void
    {
        $context = new ObservabilityContext;

        self::assertSame(
            'notifications',
            $context->moduleFromClassName('App\\Modules\\Core\\Notifications\\Presentation\\Jobs\\DeliverNotification'),
        );
        self::assertSame('identity', $context->moduleFromCommandName('auth:clear-resets'));
        self::assertSame('settings', $context->moduleFromCommandName('settings:warm-cache'));
        self::assertSame('shared', $context->moduleFromCommandName('queue:work'));
    }

    public function test_laravel_runtime_events_apply_observability_context(): void
    {
        Context::flush();

        event(new CommandStarting('settings:warm-cache', new ArrayInput([]), new NullOutput));

        self::assertSame('cli', Context::get('source'));
        self::assertSame('cli.command', Context::get('event_name'));
        self::assertSame('settings', Context::get('module'));
        self::assertSame('settings:warm-cache', Context::get('command'));
        self::assertIsString(Context::get('correlation_id'));

        Context::flush();
        Context::add('correlation_id', 'request-3');
        $mutex = $this->createMock(EventMutex::class);
        $task = new Event($mutex, 'notifications:prune');
        $task->description = 'Prune notifications';

        event(new ScheduledTaskStarting($task));

        self::assertSame('request-3', Context::get('correlation_id'));
        self::assertSame('scheduler', Context::get('source'));
        self::assertSame('scheduler.task', Context::get('event_name'));
        self::assertSame('notifications', Context::get('module'));
        self::assertSame('Prune notifications', Context::get('task'));

        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn('App\\Modules\\Core\\Notifications\\Presentation\\Jobs\\DeliverNotification');
        $job->method('getQueue')->willReturn('notifications');
        $job->method('payload')->willReturn([
            'illuminate:log:context' => [
                'data' => ['correlation_id' => serialize('request-4')],
                'hidden' => [],
            ],
        ]);

        event(new JobProcessing('redis', $job));

        self::assertSame('request-4', Context::get('correlation_id'));
        self::assertSame('queue', Context::get('source'));
        self::assertSame('queue.job', Context::get('event_name'));
        self::assertSame('notifications', Context::get('module'));
        self::assertSame('notifications', Context::get('queue'));
        self::assertSame('redis', Context::get('queue_connection'));
    }

    public function test_queue_observability_context_propagates_optional_causation_and_actor_context(): void
    {
        Context::flush();

        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn('App\\Modules\\Core\\Notifications\\Presentation\\Jobs\\DeliverNotification');
        $job->method('getQueue')->willReturn('notifications');
        $job->method('payload')->willReturn([
            'illuminate:log:context' => [
                'data' => [
                    'correlation_id' => serialize('request-5'),
                    'causation_id' => serialize('event-9'),
                    'actor_public_id' => serialize('01ACTOR'),
                    'team_public_id' => serialize('01TEAM'),
                ],
                'hidden' => [],
            ],
        ]);

        event(new JobProcessing('redis', $job));

        self::assertSame('request-5', Context::get('correlation_id'));
        self::assertSame('event-9', Context::get('causation_id'));
        self::assertSame('01ACTOR', Context::get('actor_public_id'));
        self::assertSame('01TEAM', Context::get('team_public_id'));
        self::assertSame('queue', Context::get('source'));
    }
}
