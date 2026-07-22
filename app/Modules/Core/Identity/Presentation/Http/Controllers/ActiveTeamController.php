<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ActiveTeamController
{
    public function __construct(
        private AuditRecorder $audit,
        private UserSessionRegistry $sessions,
    ) {}

    public function select(Request $request): Response|RedirectResponse
    {
        $teams = $this->assignedTeams($request);

        if (count($teams) === 1) {
            $request->session()->put('active_team_public_id', $teams[0]['publicId']);
            $this->sessions->touch($request);

            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('Teams/Select', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teamPublicId = $this->validatedTeamPublicId($request);
        $previous = $request->session()->get('active_team_public_id');

        $request->session()->put('active_team_public_id', $teamPublicId);
        $this->sessions->touch($request);
        $this->recordSwitchAudit($request, is_string($previous) ? $previous : null, $teamPublicId);

        return redirect()->intended(route('dashboard'))->with('flash.messages', [
            FlashMessage::success('flash.auth.active_team_selected'),
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $teamPublicId = $this->validatedTeamPublicId($request);
        $previous = $request->session()->get('active_team_public_id');

        $request->session()->put('active_team_public_id', $teamPublicId);
        $this->sessions->touch($request);
        $this->recordSwitchAudit($request, is_string($previous) ? $previous : null, $teamPublicId);

        return back()->with('flash.messages', [
            FlashMessage::success('flash.auth.active_team_switched'),
        ]);
    }

    /**
     * @return list<array{publicId: string, name: string}>
     */
    private function assignedTeams(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');

        if (! is_string($userPublicId)) {
            return [];
        }

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
            ->orderBy('teams.name')
            ->get(['teams.public_id', 'teams.name'])
            ->all() as $team) {
            $teams[] = [
                'publicId' => self::stringValue($team, 'public_id'),
                'name' => self::stringValue($team, 'name'),
            ];
        }

        return $teams;
    }

    private function validatedTeamPublicId(Request $request): string
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
        ]);
        $teamPublicId = is_array($validated) ? $request->string('team_public_id')->toString() : '';

        foreach ($this->assignedTeams($request) as $team) {
            if ($team['publicId'] === $teamPublicId) {
                return $teamPublicId;
            }
        }

        throw ValidationException::withMessages([
            'team_public_id' => __('validation.custom.team_public_id.available'),
        ]);
    }

    private static function stringValue(object $record, string $property): string
    {
        $value = $record->{$property} ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function recordSwitchAudit(Request $request, ?string $before, string $after): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');

        $this->audit->record(new AuditEvent(
            module: 'identity',
            action: 'session.active_team_switched',
            result: 'succeeded',
            source: 'ui',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: 'team',
            targetPublicId: $after,
            before: ['active_team_public_id' => $before],
            after: ['active_team_public_id' => $after],
            teamPublicId: $after,
            security: true,
            securityCategory: SecurityAuditCategory::Session,
        ));
    }
}
