<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PermissionAdministrationController
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private EffectivePermissionChecker $checker,
        private ModuleActivationService $activation,
        private ModuleKeyResolver $moduleKeys,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::PERMISSIONS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $databasePermissions = DB::table(AuthorizationDatabaseTable::PERMISSIONS)
            ->get(['id', 'public_id', 'name', 'display_name', 'guard_name', 'created_at', 'updated_at'])
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
            $displayName = $values['display_name'] ?? null;
            $guard = $values['guard_name'] ?? 'web';
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';

            $moduleKey = $this->moduleKeys->forPermission($permission->name);
            $moduleState = is_string($teamPublicId) ? $this->activation->effectiveState($moduleKey, $this->teamId($teamPublicId)) : null;

            return [
                'id' => is_numeric($id) ? (int) $id : null,
                'publicId' => is_string($publicId) ? $publicId : '',
                'name' => $permission->name,
                'displayName' => is_string($displayName) && $displayName !== '' && $displayName !== $permission->name ? $displayName : ($permission->displayName ?? $this->humanizeName($permission->name)),
                'guard' => is_string($guard) ? $guard : 'web',
                'description' => $permission->description,
                'module' => $moduleKey,
                'teamScoped' => $permission->teamScoped,
                'moduleActivation' => $moduleState?->effectiveEnabled === false ? 'inactive' : 'active',
                'assigned' => $allowed,
                'effective' => $allowed,
                'ineffectiveReason' => $decision !== null && ! $decision->allowed ? $decision->reason : null,
                'createdAt' => is_string($createdAt) ? $createdAt : '',
                'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
            ];
        }, $this->permissions->all());

        $moduleOptions = $this->moduleOptions($permissionRows);
        $filters = $this->filters($request, $moduleOptions);
        $result = $this->tables->process($this->applyFilters($permissionRows, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Authorization/Permissions', [
            'permissions' => $result->rows,
            'filterOptions' => [
                'modules' => $moduleOptions,
            ],
            'table' => $table,
        ]);
    }

    /**
     * @param  list<string>  $modules
     * @return array{module: string, activation: string, teamScoped: string, assigned: string, effective: string}
     */
    private function filters(Request $request, array $modules): array
    {
        return [
            'module' => $this->oneOf($request->query('module'), ['all', ...$modules]),
            'activation' => $this->oneOf($request->query('activation'), ['all', 'active', 'inactive']),
            'teamScoped' => $this->oneOf($request->query('teamScoped'), ['all', 'yes', 'no']),
            'assigned' => $this->oneOf($request->query('assigned'), ['all', 'yes', 'no']),
            'effective' => $this->oneOf($request->query('effective'), ['all', 'yes', 'no']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function moduleOptions(array $rows): array
    {
        $modules = array_values(array_unique(array_filter(
            array_map(static fn (array $row): mixed => $row['module'] ?? null, $rows),
            'is_string',
        )));

        sort($modules);

        return $modules;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{module: string, activation: string, teamScoped: string, assigned: string, effective: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['module'] !== 'all' && $row['module'] !== $filters['module']) {
                return false;
            }

            if ($filters['activation'] !== 'all' && $row['moduleActivation'] !== $filters['activation']) {
                return false;
            }

            if ($filters['teamScoped'] !== 'all' && $row['teamScoped'] !== ($filters['teamScoped'] === 'yes')) {
                return false;
            }

            if ($filters['assigned'] !== 'all' && $row['assigned'] !== ($filters['assigned'] === 'yes')) {
                return false;
            }

            return $filters['effective'] === 'all' || $row['effective'] === ($filters['effective'] === 'yes');
        }));
    }

    private function teamId(string $teamPublicId): ?int
    {
        $teamId = DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($teamId) ? (int) $teamId : null;
    }

    private function humanizeName(string $name): string
    {
        return str($name)->replace(['.', '-', '_'], ' ')->headline()->toString();
    }
}
