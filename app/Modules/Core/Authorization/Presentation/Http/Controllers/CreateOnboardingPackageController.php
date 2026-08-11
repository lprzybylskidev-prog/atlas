<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationManager;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateOnboardingPackageController
{
    public function __construct(
        private UserTeamAuthorizationManager $authorization,
        private TeamLookup $teams,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Authorization/Packages/Create', [
            'roleOptions' => $this->authorization->roleOptions(),
            'permissionOptions' => $this->authorization->permissionOptions(),
            'rolePermissionMap' => $this->authorization->rolePermissionMap(),
            'teamOptions' => $this->teamOptions(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teamOptions(): array
    {
        $teams = [];

        foreach ($this->teams->allSummaries() as $team) {
            if ($team->active) {
                $teams[] = ['value' => $team->publicId, 'label' => $team->name];
            }
        }

        return $teams;
    }
}
