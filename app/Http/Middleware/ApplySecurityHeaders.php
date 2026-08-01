<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplySecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', config()->string('atlas.security.headers.referrer_policy'));
        $response->headers->set('Permissions-Policy', config()->string('atlas.security.headers.permissions_policy'));
        $response->headers->set(
            'Content-Security-Policy',
            self::policyWithNonce(config()->string('atlas.security.headers.content_security_policy'), $nonce),
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private static function policyWithNonce(string $policy, string $nonce): string
    {
        return self::directiveWithNonce($policy, 'script-src', $nonce);
    }

    private static function directiveWithNonce(string $policy, string $directive, string $nonce): string
    {
        $nonceSource = "'nonce-{$nonce}'";
        $pattern = sprintf('/(^|;)\\s*(%s)\\s+([^;]*)/i', preg_quote($directive, '/'));

        if (preg_match($pattern, $policy) !== 1) {
            return rtrim($policy, " \t\n\r\0\x0B;")."; {$directive} 'self' {$nonceSource}";
        }

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use ($nonceSource): string {
                $sources = preg_split('/\s+/', trim((string) $matches[3]), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                if (! in_array($nonceSource, $sources, true)) {
                    $sources[] = $nonceSource;
                }

                return $matches[1].' '.$matches[2].' '.implode(' ', $sources);
            },
            $policy,
            1,
        ) ?? $policy;
    }
}
