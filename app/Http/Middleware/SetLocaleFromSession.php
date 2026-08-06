<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use App\Modules\Core\Teams\Application\Public\Contracts\TeamLookup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetLocaleFromSession
{
    private const COOKIE_KEY = 'atlas_locale';

    public function __construct(
        private EffectiveSettings $settings,
        private TeamLookup $teams,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guestLocale = $request->cookie(self::COOKIE_KEY);
        $userId = $request->user()?->getAuthIdentifier();
        $teamId = $this->activeTeamId($request);

        app()->setLocale($this->settings->locale(
            userId: is_int($userId) ? $userId : null,
            teamId: $teamId,
            guestLocale: is_string($guestLocale) ? $guestLocale : null,
        ));

        return $next($request);
    }

    private function activeTeamId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $teamPublicId = $request->session()->get('active_team_public_id');

        if (! is_string($teamPublicId)) {
            return null;
        }

        return $this->teams->internalIdForPublicId($teamPublicId);
    }
}
