<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ActiveTeamController
{
    public function __construct(
        private AuditRecorder $audit,
        private UserSessionRegistry $sessions,
        private UserTeamMembershipManager $memberships,
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

        foreach ($this->memberships->activeMembershipsForUser($userPublicId) as $team) {
            if (! $team->teamActive) {
                continue;
            }

            $teams[] = [
                'publicId' => $team->teamPublicId,
                'name' => $team->teamName,
            ];
        }

        usort($teams, static fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

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
