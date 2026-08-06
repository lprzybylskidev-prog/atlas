<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureActiveTeamSelected
{
    public function __construct(
        private UserTeamMembershipManager $memberships,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');

        if (! $request->hasSession() || ! is_string($userPublicId)) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), ['team.select', 'team.select.store', 'team.switch', 'logout'], true)) {
            return $next($request);
        }

        $activeTeamPublicId = $request->session()->get('active_team_public_id');

        if (is_string($activeTeamPublicId) && $this->belongsToActiveTeam($userPublicId, $activeTeamPublicId)) {
            return $next($request);
        }

        $teams = $this->assignedActiveTeams($userPublicId);

        if (count($teams) === 1) {
            $request->session()->put('active_team_public_id', $teams[0]['publicId']);

            return $next($request);
        }

        if (count($teams) > 1) {
            return redirect()->route('team.select');
        }

        $request->session()->forget('active_team_public_id');

        return $next($request);
    }

    /**
     * @return list<array{publicId: string, name: string}>
     */
    private function assignedActiveTeams(string $userPublicId): array
    {
        $teams = [];

        foreach ($this->memberships->activeMembershipsForUser($userPublicId) as $team) {
            if (! $team->teamActive) {
                continue;
            }

            $teams[] = [
                'publicId' => $team->teamPublicId,
                'name' => $team->teamName,
            ];
        }

        return $teams;
    }

    private function belongsToActiveTeam(string $userPublicId, string $teamPublicId): bool
    {
        foreach ($this->memberships->activeMembershipsForUser($userPublicId) as $team) {
            if ($team->teamActive && $team->teamPublicId === $teamPublicId) {
                return true;
            }
        }

        return false;
    }
}
