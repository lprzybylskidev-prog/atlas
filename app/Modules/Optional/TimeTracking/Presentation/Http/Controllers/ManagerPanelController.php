<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ManagerPanelController
{
    public function __construct(private ManagerHierarchy $hierarchy) {}

    public function __invoke(Request $request): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            throw new HttpException(403);
        }

        $scope = $this->hierarchy->scopeFor($teamPublicId, $userPublicId);

        if ($scope->visibleUserPublicIds === []) {
            throw new HttpException(403);
        }

        return Inertia::render('Manager/Panel');
    }
}
