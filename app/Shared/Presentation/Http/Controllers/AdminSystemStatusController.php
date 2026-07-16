<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminSystemStatusController
{
    public function __construct(private ModuleGate $moduleGate) {}

    public function __invoke(Request $request): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $userPublicId = is_string($userPublicId) ? $userPublicId : null;
        $teamPublicId = is_string($teamPublicId) ? $teamPublicId : null;

        return Inertia::render('Admin/SystemStatus', [
            'availability' => [
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.identity',
                    request: new ModuleAccessRequest(
                        moduleKey: 'identity',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status',
                    ),
                ),
                $this->availabilityEntry(
                    elementKey: 'admin.system-status.search',
                    request: new ModuleAccessRequest(
                        moduleKey: 'search',
                        activeTeamPublicId: $teamPublicId,
                        userPublicId: $userPublicId,
                        requiredPermission: 'admin.system-status',
                    ),
                ),
            ],
        ]);
    }

    /**
     * @return array{elementKey: string, reason: string}
     */
    private function availabilityEntry(string $elementKey, ModuleAccessRequest $request): array
    {
        $decision = $this->moduleGate->inspect($request);

        return [
            'elementKey' => $elementKey,
            'reason' => match ($decision->denialReason) {
                null => 'available',
                ModuleAccessDenialReason::PermissionDenied => 'permission-denied',
                ModuleAccessDenialReason::InvalidActiveTeam => 'active-team-required',
                default => 'module-inactive',
            },
        ];
    }
}
