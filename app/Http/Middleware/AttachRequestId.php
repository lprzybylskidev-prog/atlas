<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestId
{
    public const HEADER = 'X-Request-Id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        Context::add('request_id', $requestId);
        Context::add('correlation_id', $requestId);
        Log::withContext([
            'request_id' => $requestId,
            'correlation_id' => $requestId,
        ]);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get(self::HEADER);

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9_.:-]{8,128}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::ulid();
    }
}
