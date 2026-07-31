<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminRolesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::ROLES;
    }

    public function tableName(): string
    {
        return 'Admin roles';
    }

    public function owningModuleKey(): string
    {
        return 'authorization';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-roles-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'displayName' => 'Display name',
            'name' => 'Technical name',
            'guard' => 'Guard',
            'permissionsCount' => 'Permissions',
            'assignedUsersCount' => 'Assigned users',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_values(DB::table(DatabaseTable::ROLES)
            ->leftJoin(DatabaseTable::ROLE_HAS_PERMISSIONS, 'roles.id', '=', 'role_has_permissions.role_id')
            ->select(
                'roles.id',
                'roles.public_id',
                'roles.name',
                'roles.display_name',
                'roles.guard_name',
                'roles.created_at',
                'roles.updated_at',
                DB::raw('count(role_has_permissions.permission_id) as permissions_count'),
                DB::raw('(select count(*) from '.DatabaseTable::MODEL_HAS_ROLES.' where model_has_roles.role_id = roles.id) as assigned_users_count'),
            )
            ->groupBy('roles.id', 'roles.public_id', 'roles.name', 'roles.display_name', 'roles.guard_name', 'roles.created_at', 'roles.updated_at')
            ->get()
            ->map(static fn (object $role): array => self::roleRow($role))
            ->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $assignment = self::filterValue($request, 'assignment');

            if ($assignment === 'assigned' && self::intValue($row['assignedUsersCount'] ?? 0) <= 0) {
                return false;
            }

            if ($assignment === 'unassigned' && self::intValue($row['assignedUsersCount'] ?? 0) > 0) {
                return false;
            }

            $permissions = self::filterValue($request, 'permissions');

            if ($permissions === 'with' && self::intValue($row['permissionsCount'] ?? 0) <= 0) {
                return false;
            }

            if ($permissions === 'without' && self::intValue($row['permissionsCount'] ?? 0) > 0) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array{id: int|null, publicId: string, name: string, displayName: string, guard: string, permissionsCount: int, assignedUsersCount: int, createdAt: string, updatedAt: string}
     */
    private static function roleRow(object $role): array
    {
        $values = get_object_vars($role);
        $id = $values['id'] ?? null;
        $publicId = $values['public_id'] ?? '';
        $name = $values['name'] ?? '';
        $displayName = $values['display_name'] ?? '';
        $guard = $values['guard_name'] ?? '';
        $createdAt = $values['created_at'] ?? '';
        $updatedAt = $values['updated_at'] ?? '';
        $count = $values['permissions_count'] ?? 0;
        $assignedUsersCount = $values['assigned_users_count'] ?? 0;

        return [
            'id' => is_numeric($id) ? (int) $id : null,
            'publicId' => is_string($publicId) ? $publicId : '',
            'name' => is_string($name) ? $name : '',
            'displayName' => self::displayName($displayName, $name),
            'guard' => is_string($guard) ? $guard : '',
            'permissionsCount' => is_numeric($count) ? (int) $count : 0,
            'assignedUsersCount' => is_numeric($assignedUsersCount) ? (int) $assignedUsersCount : 0,
            'createdAt' => is_string($createdAt) ? $createdAt : '',
            'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
        ];
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function displayName(mixed $displayName, mixed $name): string
    {
        $technicalName = is_string($name) ? $name : '';

        return is_string($displayName) && $displayName !== '' && $displayName !== $technicalName
            ? $displayName
            : str($technicalName)->replace(['.', '-', '_'], ' ')->headline()->toString();
    }
}
