<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EditOnboardingPackageController
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
        private PermissionCatalogRegistry $permissions,
    ) {}

    public function __invoke(string $package): Response
    {
        $definition = $this->packages->getByPublicId($package);

        if ($definition === null) {
            abort(404);
        }

        return Inertia::render('Admin/Authorization/Packages/Edit', [
            'package' => [
                'publicId' => $definition->publicId,
                'teamPublicId' => $definition->teamPublicId,
                'teamName' => $definition->teamName,
                'name' => $definition->name,
                'label' => $definition->label,
                'initialRoles' => $definition->initialRoleNames,
                'directPermissions' => $definition->directPermissionNames,
            ],
            'roleOptions' => DB::table('roles')
                ->where('guard_name', 'web')
                ->whereNull(config()->string('permission.column_names.team_foreign_key'))
                ->orderBy('name')
                ->pluck('name')
                ->filter(static fn (mixed $role): bool => is_string($role))
                ->values()
                ->all(),
            'permissionOptions' => $this->permissions->names(),
        ]);
    }
}
