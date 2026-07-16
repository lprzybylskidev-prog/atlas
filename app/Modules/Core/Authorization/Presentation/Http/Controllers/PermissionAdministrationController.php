<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PermissionAdministrationController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private EffectivePermissionChecker $checker,
    ) {}

    public function __invoke(Request $request): Response
    {
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $databasePermissions = DB::table('permissions')
            ->get(['id', 'public_id', 'name', 'guard_name', 'created_at', 'updated_at'])
            ->keyBy('name');

        $permissionRows = array_map(function ($permission) use ($databasePermissions, $userPublicId, $teamPublicId): array {
            $decision = is_string($userPublicId) && is_string($teamPublicId)
                ? $this->checker->check(new EffectivePermissionRequest($userPublicId, $permission->name, $teamPublicId))
                : null;

            $allowed = $decision === null ? false : $decision->allowed;
            $databasePermission = $databasePermissions->get($permission->name);
            $values = is_object($databasePermission) ? get_object_vars($databasePermission) : [];
            $id = $values['id'] ?? null;
            $publicId = $values['public_id'] ?? '';
            $guard = $values['guard_name'] ?? 'web';
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';

            return [
                'id' => is_numeric($id) ? (int) $id : null,
                'publicId' => is_string($publicId) ? $publicId : '',
                'name' => $permission->name,
                'guard' => is_string($guard) ? $guard : 'web',
                'description' => $permission->description,
                'module' => explode('.', $permission->name)[0] ?: 'core',
                'teamScoped' => $permission->teamScoped,
                'moduleActivation' => 'active',
                'assigned' => $allowed,
                'effective' => $allowed,
                'ineffectiveReason' => $decision !== null && ! $decision->allowed ? $decision->reason : null,
                'createdAt' => is_string($createdAt) ? $createdAt : '',
                'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
            ];
        }, $this->permissions->all());

        usort($permissionRows, static fn (array $first, array $second): int => $first['name'] <=> $second['name']);

        return Inertia::render('Admin/Authorization/Permissions', [
            'permissions' => $permissionRows,
        ]);
    }
}
