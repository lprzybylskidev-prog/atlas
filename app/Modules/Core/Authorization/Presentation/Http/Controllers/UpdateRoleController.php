<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Audit\Application\Public\Enums\SecurityAuditCategory;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final readonly class UpdateRoleController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        $record = DB::table(DatabaseTable::ROLES)
            ->where('name', $role)
            ->where('guard_name', 'web')
            ->first(['id', 'public_id', 'name', 'display_name']);

        if (! is_object($record)) {
            abort(404);
        }

        $values = get_object_vars($record);
        $roleId = $values['id'] ?? null;
        $rolePublicId = is_string($values['public_id'] ?? null) ? $values['public_id'] : '';
        $beforePermissions = is_numeric($roleId) ? $this->permissionNames((int) $roleId) : [];

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_.-]+$/',
                $this->uniqueRoleNameRule(is_numeric($roleId) ? (int) $roleId : null),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($this->permissions->names())],
        ]);
        $validated = is_array($validated) ? $validated : [];

        $name = is_string($validated['name'] ?? null) ? $validated['name'] : '';
        $displayName = is_string($validated['display_name'] ?? null) ? $validated['display_name'] : $name;
        $permissionNames = $this->stringList($validated, 'permissions');

        DB::transaction(function () use ($roleId, $name, $displayName, $permissionNames): void {
            DB::table(DatabaseTable::ROLES)->where('id', $roleId)->update([
                'name' => $name,
                'display_name' => $displayName,
                'updated_at' => now(),
            ]);

            if (is_numeric($roleId)) {
                $this->syncRolePermissions((int) $roleId, $permissionNames);
            }
        });

        $this->recordAudit($request, 'authorization.role_updated', 'succeeded', $rolePublicId, [
            'name' => is_string($values['name'] ?? null) ? $values['name'] : $role,
            'display_name' => is_string($values['display_name'] ?? null) ? $values['display_name'] : null,
            'permissions' => $beforePermissions,
        ], [
            'name' => $name,
            'display_name' => $displayName,
            'permissions' => $permissionNames,
        ]);

        return redirect()
            ->route('admin.authorization.roles.edit', ['role' => $name])
            ->with('flash.messages', [
                FlashMessage::success('flash.authorization.role_updated'),
            ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function syncRolePermissions(int $roleId, array $permissionNames): void
    {
        DB::table(DatabaseTable::ROLE_HAS_PERMISSIONS)->where('role_id', $roleId)->delete();

        $permissionIds = DB::table(DatabaseTable::PERMISSIONS)
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        foreach ($permissionIds as $permissionId) {
            DB::table(DatabaseTable::ROLE_HAS_PERMISSIONS)->insert([
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

    private function uniqueRoleNameRule(?int $exceptRoleId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($exceptRoleId): void {
            if (! is_string($value)) {
                return;
            }

            $exists = DB::table(DatabaseTable::ROLES)
                ->where('name', $value)
                ->where('guard_name', 'web')
                ->whereNull(config()->string('permission.column_names.team_foreign_key'))
                ->when($exceptRoleId !== null, static fn ($query) => $query->where('id', '<>', $exceptRoleId))
                ->exists();

            if (! $exists) {
                return;
            }

            $fail('The '.$attribute.' has already been taken.');
        };
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
