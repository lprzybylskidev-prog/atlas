<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Infrastructure\Database\DatabaseTable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveTeamSelected
{
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

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->orderBy('teams.display_name')
            ->orderBy('teams.name')
            ->get(['teams.public_id', 'teams.name', 'teams.display_name'])
            ->all() as $team) {
            $teams[] = [
                'publicId' => self::stringValue($team, 'public_id'),
                'name' => self::teamDisplayName($team),
            ];
        }

        return $teams;
    }

    private function belongsToActiveTeam(string $userPublicId, string $teamPublicId): bool
    {
        return DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.public_id', $teamPublicId)
            ->where('teams.is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->exists();
    }

    private static function stringValue(object $record, string $property): string
    {
        $value = $record->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private static function teamDisplayName(object $record): string
    {
        $displayName = self::stringValue($record, 'display_name');

        return $displayName !== '' ? $displayName : self::stringValue($record, 'name');
    }
}
