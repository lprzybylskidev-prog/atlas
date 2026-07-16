<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UserTeamMembershipController
{
    public function __construct(
        private UserTeamMembershipManager $memberships,
    ) {}

    public function store(Request $request, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string', 'exists:teams,public_id'],
        ]);

        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = is_array($validated) && is_string($validated['team_public_id'] ?? null) ? $validated['team_public_id'] : '';

        if (is_string($actorPublicId)) {
            $this->memberships->addAccess($actorPublicId, $user, $teamPublicId);
        }

        return redirect()->route('admin.users.edit', ['user' => $user])->with('success', 'Team access was added.');
    }

    public function destroy(Request $request, string $user, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $actorPublicId = data_get($request->user(), 'public_id');
        $reason = is_array($validated) && is_string($validated['reason'] ?? null) ? $validated['reason'] : '';

        if (is_string($actorPublicId)) {
            $this->memberships->removeAccess($actorPublicId, $user, $team, $reason);
        }

        return redirect()->route('admin.users.edit', ['user' => $user])->with('success', 'Team access was removed.');
    }
}
