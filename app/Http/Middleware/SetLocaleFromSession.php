<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Settings\Application\Settings\EffectiveSettings;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleFromSession
{
    private const COOKIE_KEY = 'atlas_locale';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guestLocale = $request->cookie(self::COOKIE_KEY);
        $userId = $request->user()?->getAuthIdentifier();
        $teamId = $this->activeTeamId($request);

        /** @var EffectiveSettings $settings */
        $settings = app(EffectiveSettings::class);

        app()->setLocale($settings->locale(
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

        $teamId = DB::table(DatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->value('id');

        return is_int($teamId) ? $teamId : null;
    }
}
