<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Middleware;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizeRoutePermission
{
    public function __construct(
        private EffectivePermissionChecker $permissions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userPublicId = is_object($user) ? data_get($user, 'public_id') : null;

        if (! is_string($routeName) || $routeName === '' || ! is_string($teamPublicId) || ! is_string($userPublicId)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $decision = $this->permissions->check(new EffectivePermissionRequest(
            userPublicId: $userPublicId,
            permission: $routeName,
            teamPublicId: $teamPublicId,
        ));

        if (! $decision->allowed) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
