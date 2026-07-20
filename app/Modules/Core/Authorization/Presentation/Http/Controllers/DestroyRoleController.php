<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class DestroyRoleController
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        $record = DB::table(DatabaseTable::ROLES)->where('name', $role)->where('guard_name', 'web')->first(['id', 'public_id', 'name']);
        $values = is_object($record) ? get_object_vars($record) : [];
        $roleId = $values['id'] ?? null;
        $rolePublicId = is_string($values['public_id'] ?? null) ? $values['public_id'] : $role;
        $permissions = is_numeric($roleId) ? $this->permissionNames((int) $roleId) : [];

        if (is_int($roleId) && ! DB::table(DatabaseTable::MODEL_HAS_ROLES)->where('role_id', $roleId)->exists()) {
            DB::table(DatabaseTable::ROLE_HAS_PERMISSIONS)->where('role_id', $roleId)->delete();
            DB::table(DatabaseTable::ROLES)->where('id', $roleId)->delete();

            $this->recordAudit($request, 'authorization.role_deleted', 'succeeded', $rolePublicId, [
                'name' => is_string($values['name'] ?? null) ? $values['name'] : $role,
                'permissions' => $permissions,
            ], []);
        } else {
            $this->recordAudit($request, 'authorization.role_delete_rejected', 'rejected', $rolePublicId, [
                'name' => is_string($values['name'] ?? null) ? $values['name'] : $role,
                'permissions' => $permissions,
            ], []);
        }

        return redirect()->route('admin.authorization.roles.index')->with('success', 'Role delete was attempted.');
    }

    /**
     * @return list<string>
     */
    private function permissionNames(int $roleId): array
    {
        return array_values(DB::table(DatabaseTable::ROLE_HAS_PERMISSIONS)
            ->join(DatabaseTable::PERMISSIONS, 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $roleId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->filter(static fn (mixed $permission): bool => is_string($permission))
            ->all());
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(Request $request, string $action, string $result, string $targetPublicId, array $before, array $after): void
    {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->audit->record(new AuditEvent(
            module: 'authorization',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: 'role',
            targetPublicId: $targetPublicId,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            before: $before,
            after: $after,
            security: true,
            securityCategory: SecurityAuditCategory::Authorization,
        ));
    }
}
