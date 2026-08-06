<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateOnboardingPackageController
{
    public function __construct(
        private UserTeamAuthorizationManager $authorization,
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

        foreach (DB::table(TeamsDatabaseTable::TEAMS)->where('is_active', true)->orderBy('name')->get(['public_id', 'name', 'display_name']) as $team) {
            $values = get_object_vars($team);
            $publicId = $values['public_id'] ?? '';
            $name = $values['name'] ?? '';
            $displayName = $values['display_name'] ?? '';

            if (is_string($publicId) && is_string($name)) {
                $teams[] = ['value' => $publicId, 'label' => is_string($displayName) && $displayName !== '' ? $displayName : $name];
            }
        }

        return $teams;
    }
}
