<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class StoreRoleController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.-]+$/', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->permissions->names())],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $name = is_string($validated['name'] ?? null) ? $validated['name'] : '';

        $rolePublicId = (string) Str::ulid();
        $permissionNames = $this->stringList($validated, 'permissions');

        DB::transaction(function () use ($name, $rolePublicId, $permissionNames): void {
            $roleId = DB::table('roles')->insertGetId([
                'public_id' => $rolePublicId,
                'name' => $name,
                'guard_name' => 'web',
                config()->string('permission.column_names.team_foreign_key') => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncRolePermissions($roleId, $permissionNames);
        });

        $this->recordAudit($request, 'authorization.role_created', 'succeeded', $rolePublicId, [], [
            'name' => $name,
            'permissions' => $permissionNames,
        ]);

        return redirect()->route('admin.authorization.roles.index')->with('success', 'Role was created.');
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function syncRolePermissions(int $roleId, array $permissionNames): void
    {
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values, string $key): array
    {
        $value = $values[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
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
            securityCategory: 'authorization',
        ));
    }
}
