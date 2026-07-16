<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EditRoleController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
    ) {}

    public function __invoke(string $role): Response
    {
        $record = DB::table('roles')
            ->where('name', $role)
            ->where('guard_name', 'web')
            ->first(['id', 'name', 'guard_name']);

        if (! is_object($record)) {
            abort(404);
        }

        $values = get_object_vars($record);

        return Inertia::render('Admin/Authorization/Roles/Edit', [
            'role' => [
                'name' => is_string($values['name'] ?? null) ? $values['name'] : '',
                'guard' => is_string($values['guard_name'] ?? null) ? $values['guard_name'] : 'web',
                'permissions' => $this->rolePermissionNames($values['id'] ?? null),
            ],
            'permissionOptions' => $this->permissions->names(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function rolePermissionNames(mixed $roleId): array
    {
        if (! is_numeric($roleId)) {
            return [];
        }

        return array_values(DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', (int) $roleId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->filter(static fn (mixed $permission): bool => is_string($permission))
            ->all());
    }
}
