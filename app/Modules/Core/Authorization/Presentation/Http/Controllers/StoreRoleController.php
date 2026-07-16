<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

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

        DB::transaction(function () use ($name, $validated): void {
            $roleId = DB::table('roles')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'name' => $name,
                'guard_name' => 'web',
                config()->string('permission.column_names.team_foreign_key') => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncRolePermissions($roleId, $this->stringList($validated, 'permissions'));
        });

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
}
