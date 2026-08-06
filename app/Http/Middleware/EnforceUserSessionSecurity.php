<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Modules\Core\Identity\Application\Sessions\SessionLimitResolver;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Presentation\Support\FlashMessage;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceUserSessionSecurity
{
    public function __construct(
        private SessionLimitResolver $limits,
        private UserSessionRegistry $sessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $request->hasSession() || ! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        $now = Carbon::now();
        $createdAt = $this->timestamp($session->get('atlas_session_created_at'));
        $lastActivityAt = $this->timestamp($session->get('atlas_session_last_activity_at'));

        if ($createdAt === null) {
            $createdAt = $now;
            $session->put('atlas_session_created_at', $createdAt->toIso8601String());
        }

        if ($lastActivityAt === null) {
            $lastActivityAt = $now;
        }

        $teamPublicId = $request->session()->get('active_team_public_id');
        $limits = $this->limits->limitsFor($user, is_string($teamPublicId) ? $teamPublicId : null);

        if ($createdAt->copy()->addMinutes($limits['maximum'])->lte($now)) {
            return $this->expire($request, 'flash.auth.session_expired_lifetime');
        }

        if ($request->route()?->getName() !== 'time-tracking.activity.record'
            && $lastActivityAt->copy()->addMinutes($limits['inactivity'])->lte($now)
        ) {
            return $this->expire($request, 'flash.auth.session_expired_inactivity');
        }

        $this->sessions->touch($request);

        $response = $next($request);

        if ($request->hasSession() && $request->user() instanceof User) {
            $request->session()->put('atlas_session_last_activity_at', Carbon::now()->toIso8601String());
            $this->sessions->touch($request);
        }

        return $response;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function expire(Request $request, string $messageKey): RedirectResponse
    {
        $sessionId = $request->session()->getId();

        if ($sessionId !== '') {
            $this->sessions->terminate($sessionId);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('flash.messages', [
            FlashMessage::error($messageKey),
        ]);
    }
}
