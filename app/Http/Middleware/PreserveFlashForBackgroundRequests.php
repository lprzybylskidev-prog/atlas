<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class PreserveFlashForBackgroundRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('HTTP middleware pipeline did not return a response.');
        }

        if ($request->expectsJson() && ! $request->headers->has('X-Inertia') && $request->hasSession()) {
            $request->session()->reflash();
        }

        return $response;
    }
}
