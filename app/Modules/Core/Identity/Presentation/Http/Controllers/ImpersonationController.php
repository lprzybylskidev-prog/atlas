<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ImpersonationController
{
    public function __construct(
        private ImpersonationManager $impersonation,
        private UserTeamMembershipManager $memberships,
    ) {}

    public function create(Request $request, string $user): Response
    {
        $actor = $request->user();
        $eligibility = $actor instanceof User ? $this->impersonation->eligibility($request, (string) $actor->public_id, $user) : null;

        if ($eligibility === null || ! $eligibility->canStart) {
            abort(403);
        }

        return Inertia::render('Admin/Impersonation/Start', [
            'target' => $this->target(
                User::query()
                    ->where('public_id', $user)
                    ->firstOrFail(['public_id', 'name', 'email', 'account_sensitivity']),
            ),
            'teams' => $this->teams($user),
            'requiresSensitiveOverride' => $eligibility->requiresSensitiveOverride,
        ]);
    }

    public function store(Request $request, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
            'override_sensitive' => ['sometimes', 'boolean'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $actor = $request->user();

        if (! $actor instanceof User || ! $this->impersonation->start(
            request: $request,
            actor: $actor,
            targetPublicId: $user,
            teamPublicId: is_string($validated['team_public_id'] ?? null) ? $validated['team_public_id'] : '',
            reason: is_string($validated['reason'] ?? null) ? $validated['reason'] : '',
            overrideSensitive: ($validated['override_sensitive'] ?? false) === true,
        )) {
            throw ValidationException::withMessages([
                'user' => 'Impersonation cannot be started for this account.',
            ]);
        }

        return redirect()->route('dashboard')->with('flash.messages', [
            FlashMessage::success('flash.auth.impersonation_started'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->impersonation->stop($request);

        return redirect()->route('admin.system-status')->with('flash.messages', [
            FlashMessage::success('flash.auth.impersonation_ended'),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teams(string $userPublicId): array
    {
        $teams = [];

        foreach ($this->memberships->activeMembershipsForUser($userPublicId) as $team) {
            if (! $team->teamActive) {
                continue;
            }

            $teams[] = [
                'value' => $team->teamPublicId,
                'label' => $team->teamName,
            ];
        }

        usort($teams, static fn (array $first, array $second): int => strcmp($first['label'], $second['label']));

        return $teams;
    }

    /**
     * @return array{publicId: string, name: string, email: string, accountSensitivity: string}
     */
    private function target(User $user): array
    {
        return [
            'publicId' => (string) $user->public_id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'accountSensitivity' => (string) $user->account_sensitivity,
        ];
    }
}
