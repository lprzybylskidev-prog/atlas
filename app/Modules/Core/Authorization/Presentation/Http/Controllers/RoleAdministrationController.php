<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RoleAdministrationController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::ROLES);
        $state = TableState::fromRequest($request, $definition);
        $filters = $this->filters($request);
        [$userId, $teamId] = $this->context->userTeam($request);
        $roles = array_values(DB::table(DatabaseTable::ROLES)
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
        $result = $this->tables->process($this->applyFilters($roles, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Authorization/Roles', [
            'roles' => $result->rows,
            'table' => $table,
        ]);
    }

    /**
     * @return array{assignment: string, permissions: string}
     */
    private function filters(Request $request): array
    {
        $assignment = $request->query('assignment');
        $permissions = $request->query('permissions');

        return [
            'assignment' => in_array($assignment, ['assigned', 'unassigned'], true) ? $assignment : 'all',
            'permissions' => in_array($permissions, ['with', 'without'], true) ? $permissions : 'all',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{assignment: string, permissions: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['assignment'] === 'assigned' && self::intValue($row['assignedUsersCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['assignment'] === 'unassigned' && self::intValue($row['assignedUsersCount'] ?? 0) > 0) {
                return false;
            }

            if ($filters['permissions'] === 'with' && self::intValue($row['permissionsCount'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['permissions'] === 'without' && self::intValue($row['permissionsCount'] ?? 0) > 0) {
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

    private static function displayName(mixed $displayName, mixed $name): string
    {
        $technicalName = is_string($name) ? $name : '';

        return is_string($displayName) && $displayName !== '' && $displayName !== $technicalName
            ? $displayName
            : str($technicalName)->replace(['.', '-', '_'], ' ')->headline()->toString();
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
