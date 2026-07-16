<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
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
            'roleOptions' => DB::table('roles')
                ->where('guard_name', 'web')
                ->whereNull(config()->string('permission.column_names.team_foreign_key'))
                ->orderBy('name')
                ->pluck('name')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->values()
                ->all(),
            'permissionOptions' => $this->permissions->names(),
        ]);
    }
}
