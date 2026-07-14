<?php

declare(strict_types=1);

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleFromSession;
use App\Shared\Infrastructure\Console\ResetDemoEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ResetDemoEnvironment::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AttachRequestId::class);
        $middleware->web(append: [
            SetLocaleFromSession::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
