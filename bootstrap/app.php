<?php

declare(strict_types=1);

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnforceUserSessionSecurity;
use App\Http\Middleware\EnsureActiveTeamSelected;
use App\Http\Middleware\ForceAdminLocale;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleFromSession;
use App\Modules\Core\Authorization\Presentation\Http\Middleware\AuthorizeRoutePermission;
use App\Modules\Core\Notifications\Presentation\Console\PruneNotificationsCommand;
use App\Modules\Core\Notifications\Presentation\Console\PublishRealtimeEventCommand;
use App\Modules\Core\Notifications\Presentation\Console\SendNotificationCommand;
use App\Shared\Infrastructure\Console\ResetDemoEnvironment;
use App\Shared\Presentation\Console\ApplyDueModuleActivationSchedules;
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
            __DIR__.'/../routes/web/locale.php',
            __DIR__.'/../routes/web/application.php',
            __DIR__.'/../routes/web/admin.php',
        ],
    )
    ->withCommands([
        ApplyDueModuleActivationSchedules::class,
        PruneNotificationsCommand::class,
        PublishRealtimeEventCommand::class,
        ResetDemoEnvironment::class,
        SendNotificationCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'route.permission' => AuthorizeRoutePermission::class,
        ]);

        $middleware->append(AttachRequestId::class);
        $middleware->web(append: [
            SetLocaleFromSession::class,
            ForceAdminLocale::class,
            EnforceUserSessionSecurity::class,
            EnsureActiveTeamSelected::class,
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
