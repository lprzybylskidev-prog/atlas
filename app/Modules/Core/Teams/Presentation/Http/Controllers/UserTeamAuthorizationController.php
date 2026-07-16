<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class UserTeamAuthorizationController
{
    public function __construct(
        private UserTeamAuthorizationManager $authorization,
    ) {}

    public function update(Request $request, string $user, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'role_names' => ['array'],
            'role_names.*' => ['string'],
            'direct_permission_names' => ['array'],
            'direct_permission_names.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $validated = is_array($validated) ? $validated : [];

        $actorPublicId = data_get($request->user(), 'public_id');

        if (is_string($actorPublicId)) {
            $this->authorization->replaceAssignmentsForUserTeam(
                actorPublicId: $actorPublicId,
                userPublicId: $user,
                teamPublicId: $team,
                roleNames: $this->stringList($validated['role_names'] ?? []),
                directPermissionNames: $this->stringList($validated['direct_permission_names'] ?? []),
                reason: is_string($validated['reason'] ?? null) ? $validated['reason'] : null,
            );
        }

        return redirect()->route('admin.users.edit', ['user' => $user])->with('success', 'Team authorization was updated.');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, 'is_string'));
    }
}
