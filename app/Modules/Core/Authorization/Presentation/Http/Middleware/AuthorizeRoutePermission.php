<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Middleware;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if (! is_string($teamPublicId) && is_string($userPublicId) && $request->hasSession()) {
            $teamPublicId = $this->firstAssignedTeamPublicId($userPublicId);

            if (is_string($teamPublicId)) {
                $request->session()->put('active_team_public_id', $teamPublicId);
            }
        }

        if (! is_string($routeName) || $routeName === '' || ! is_string($teamPublicId) || ! is_string($userPublicId)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $decision = $this->permissions->check(new EffectivePermissionRequest(
            userPublicId: $userPublicId,
            permission: $routeName,
            teamPublicId: $teamPublicId,
        ));

        if (! $decision->allowed && in_array($decision->reason, ['authorization.active_team_invalid', 'authorization.active_team_not_assigned'], true)) {
            $fallbackTeamPublicId = $this->firstAssignedTeamPublicId($userPublicId);

            if (is_string($fallbackTeamPublicId) && $fallbackTeamPublicId !== $teamPublicId && $request->hasSession()) {
                $request->session()->put('active_team_public_id', $fallbackTeamPublicId);

                $decision = $this->permissions->check(new EffectivePermissionRequest(
                    userPublicId: $userPublicId,
                    permission: $routeName,
                    teamPublicId: $fallbackTeamPublicId,
                ));
            }
        }

        if (! $decision->allowed) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function firstAssignedTeamPublicId(string $userPublicId): ?string
    {
        $team = DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->orderBy('teams.name')
            ->value('teams.public_id');

        return is_string($team) ? $team : null;
    }
}
