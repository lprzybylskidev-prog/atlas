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
            'teamId' => 'Team ID',
            'name' => 'Role',
            'guard' => 'Guard',
            'permissionsCount' => 'Permissions',
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
                'roles.team_id',
                'roles.name',
                'roles.guard_name',
                'roles.created_at',
                'roles.updated_at',
                DB::raw('count(role_has_permissions.permission_id) as permissions_count'),
            )
            ->groupBy('roles.id', 'roles.public_id', 'roles.team_id', 'roles.name', 'roles.guard_name', 'roles.created_at', 'roles.updated_at')
            ->get()
            ->map(static fn (object $role): array => self::roleRow($role))
            ->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
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

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $guard = self::filterValue($request, 'guard');

            if ($guard !== 'all' && $row['guard'] !== $guard) {
                return false;
            }

            if (self::filterValue($request, 'scope') === 'global') {
                return $row['teamId'] === null;
            }

            if (self::filterValue($request, 'scope') === 'team') {
                return $row['teamId'] !== null;
            }

            return true;
        }));
    }
}
