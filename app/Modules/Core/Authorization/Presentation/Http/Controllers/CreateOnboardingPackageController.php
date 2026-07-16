<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateOnboardingPackageController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Authorization/Packages/Create', [
            'roleOptions' => DB::table(DatabaseTable::ROLES)
                ->where('guard_name', 'web')
                ->whereNull(config()->string('permission.column_names.team_foreign_key'))
                ->orderBy('name')
                ->pluck('name')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->values()
                ->all(),
            'permissionOptions' => $this->permissions->names(),
            'teamOptions' => $this->teamOptions(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teamOptions(): array
    {
        $teams = [];

        foreach (DB::table(DatabaseTable::TEAMS)->where('is_active', true)->orderBy('name')->get(['public_id', 'name']) as $team) {
            $values = get_object_vars($team);
            $publicId = $values['public_id'] ?? '';
            $name = $values['name'] ?? '';

            if (is_string($publicId) && is_string($name)) {
                $teams[] = ['value' => $publicId, 'label' => $name];
            }
        }

        return $teams;
    }
}
