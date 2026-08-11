<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Middleware;

use App\Modules\Core\Identity\Application\Public\Contracts\MfaRequirementChecker;
use App\Modules\Core\Identity\Application\Public\DTOs\MfaRequirementContext;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceConfiguredMfaRequirement
{
    public function __construct(private MfaRequirementChecker $requirements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $routeName = $request->route()?->getName();
        $required = $this->requirements->isRequired(new MfaRequirementContext(
            userPublicId: (string) $user->public_id,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            operation: is_string($routeName) ? $routeName : null,
            permissions: is_string($routeName) ? [$routeName] : [],
        ));

        if ($request->routeIs('two-factor.disable') && $required) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Multi-factor authentication is required for this account.');
        }

        if (! $required || $user->two_factor_confirmed_at !== null || $this->isEnrollmentRoute($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Multi-factor authentication enrollment is required.');
        }

        return redirect()->route('users.profile', ['mfa_required' => 1]);
    }

    private function isEnrollmentRoute(Request $request): bool
    {
        return $request->routeIs(
            'users.profile',
            'logout',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.qr-code',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
            'two-factor.secret-key',
        );
    }
}
