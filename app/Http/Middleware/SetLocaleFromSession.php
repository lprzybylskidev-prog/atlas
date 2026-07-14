<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleFromSession
{
    private const COOKIE_KEY = 'atlas_locale';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE_KEY);

        if (is_string($locale) && in_array($locale, $this->supportedLocales(), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        return ['pl', 'en'];
    }
}
