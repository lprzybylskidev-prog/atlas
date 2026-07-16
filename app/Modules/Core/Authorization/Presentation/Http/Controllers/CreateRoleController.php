<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CreateRoleController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Authorization/Roles/Create', [
            'permissionOptions' => $this->permissions->names(),
        ]);
    }
}
