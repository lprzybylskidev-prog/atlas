<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
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
        [$userId, $teamId] = $this->context->userTeam($request);
        $roles = array_values(DB::table(DatabaseTable::ROLES)
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
        $result = $this->tables->process($roles, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Authorization/Roles', [
            'roles' => $result->rows,
            'table' => $result->tableMeta($definition->key),
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
