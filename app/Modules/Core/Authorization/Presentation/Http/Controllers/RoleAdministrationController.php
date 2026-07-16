<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class RoleAdministrationController
{
    public function __invoke(): Response
    {
        $roles = DB::table('roles')
            ->leftJoin('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
            ->select(
                'roles.id',
                'roles.public_id',
                'roles.team_id',
                'roles.name',
                'roles.guard_name',
                'roles.created_at',
                'roles.updated_at',
                DB::raw('count(role_has_permissions.permission_id) as permissions_count'),
            )
            ->groupBy('roles.id', 'roles.public_id', 'roles.team_id', 'roles.name', 'roles.guard_name', 'roles.created_at', 'roles.updated_at')
            ->orderBy('roles.name')
            ->get()
            ->map(static fn (object $role): array => self::roleRow($role))
            ->all();

        return Inertia::render('Admin/Authorization/Roles', [
            'roles' => $roles,
        ]);
    }

    /**
     * @return array{id: int|null, publicId: string, teamId: int|null, name: string, guard: string, permissionsCount: int, createdAt: string, updatedAt: string}
     */
    private static function roleRow(object $role): array
    {
        $values = get_object_vars($role);
        $id = $values['id'] ?? null;
        $publicId = $values['public_id'] ?? '';
        $teamId = $values['team_id'] ?? null;
        $name = $values['name'] ?? '';
        $guard = $values['guard_name'] ?? '';
        $createdAt = $values['created_at'] ?? '';
        $updatedAt = $values['updated_at'] ?? '';
        $count = $values['permissions_count'] ?? 0;

        return [
            'id' => is_numeric($id) ? (int) $id : null,
            'publicId' => is_string($publicId) ? $publicId : '',
            'teamId' => is_numeric($teamId) ? (int) $teamId : null,
            'name' => is_string($name) ? $name : '',
            'guard' => is_string($guard) ? $guard : '',
            'permissionsCount' => is_numeric($count) ? (int) $count : 0,
            'createdAt' => is_string($createdAt) ? $createdAt : '',
            'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
        ];
    }
}
