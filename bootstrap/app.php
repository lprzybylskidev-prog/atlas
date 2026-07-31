<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyImpersonationContext;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\BlockProhibitedImpersonationOperations;
use App\Http\Middleware\EnforceUserSessionSecurity;
use App\Http\Middleware\EnsureActiveTeamSelected;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireAdministrativeMode;
use App\Http\Middleware\RequireHighRiskAdministrativeAuthorization;
use App\Http\Middleware\RequireImpersonationExternalEffectAcknowledgement;
use App\Http\Middleware\SetLocaleFromSession;
use App\Modules\Core\Authorization\Presentation\Http\Middleware\AuthorizeRoutePermission;
use App\Modules\Core\Files\Presentation\Console\PruneTemporaryFilesCommand;
use App\Modules\Core\Notifications\Presentation\Console\PruneNotificationsCommand;
use App\Modules\Core\Notifications\Presentation\Console\PublishRealtimeEventCommand;
use App\Modules\Core\Notifications\Presentation\Console\SendNotificationCommand;
use App\Modules\Optional\Search\Presentation\Console\RebuildSearchIndexesCommand;
use App\Shared\Infrastructure\Console\ResetDemoEnvironment;
use App\Shared\Presentation\Console\ApplyDueModuleActivationSchedules;
use App\Shared\Presentation\Console\DispatchOperationalAlertsCommand;
use App\Shared\Presentation\Console\RecordSchedulerHeartbeatCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web/auth.php',
            __DIR__.'/../routes/web/health.php',
            __DIR__.'/../routes/web/locale.php',
            __DIR__.'/../routes/web/application.php',
            __DIR__.'/../routes/web/admin.php',
        ],
    )
    ->withCommands([
        ApplyDueModuleActivationSchedules::class,
        DispatchOperationalAlertsCommand::class,
        PruneTemporaryFilesCommand::class,
        PruneNotificationsCommand::class,
        PublishRealtimeEventCommand::class,
        RecordSchedulerHeartbeatCommand::class,
        RebuildSearchIndexesCommand::class,
        ResetDemoEnvironment::class,
        SendNotificationCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('system:scheduler-heartbeat')->everyMinute()->withoutOverlapping();
        $schedule->command('system:operational-alerts')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('files:prune-temporary')->hourly()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'route.permission' => AuthorizeRoutePermission::class,
            'admin.mode' => RequireAdministrativeMode::class,
            'admin.high-risk' => RequireHighRiskAdministrativeAuthorization::class,
            'impersonation.external-effect' => RequireImpersonationExternalEffectAcknowledgement::class,
        ]);

        $middleware->append(AttachRequestId::class);
        $middleware->append(ApplySecurityHeaders::class);
        $middleware->web(append: [
            SetLocaleFromSession::class,
            EnforceUserSessionSecurity::class,
            ApplyImpersonationContext::class,
            EnsureActiveTeamSelected::class,
            BlockProhibitedImpersonationOperations::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $throwable, Request $request): Response {
            if ($request->expectsJson()) {
                return $response;
            }

            if (! in_array($response->getStatusCode(), [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $response->getStatusCode(),
            ])->toResponse($request)->setStatusCode($response->getStatusCode());
        });
    })->create();
