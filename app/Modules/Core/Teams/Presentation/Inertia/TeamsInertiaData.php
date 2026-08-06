<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Inertia;

use App\Modules\Core\Teams\Application\Public\Contracts\UserTeamMembershipManager;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;

final readonly class TeamsInertiaData implements InertiaSharedDataContributor
{
    public function __construct(
        private UserTeamMembershipManager $memberships,
    ) {}

    public function key(): string
    {
        return 'core.teams';
    }

    public function data(Request $request): array
    {
        return [
            'auth.teams' => $this->teams($request),
        ];
    }

    /**
     * @return array{active: array{publicId: string, name: string}|null, available: list<array{publicId: string, name: string}>}
     */
    private function teams(Request $request): array
    {
        $userPublicId = data_get($request->user(), 'public_id');

        if (! is_string($userPublicId)) {
            return [
                'active' => null,
                'available' => [],
            ];
        }

        $available = [];

        foreach ($this->memberships->activeMembershipsForUser($userPublicId) as $membership) {
            if (! $membership->teamActive) {
                continue;
            }

            $available[] = [
                'publicId' => $membership->teamPublicId,
                'name' => $membership->teamName,
            ];
        }

        $activePublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $active = null;

        foreach ($available as $team) {
            if (is_string($activePublicId) && $team['publicId'] === $activePublicId) {
                $active = $team;
                break;
            }
        }

        return [
            'active' => $active,
            'available' => $available,
        ];
    }
}
