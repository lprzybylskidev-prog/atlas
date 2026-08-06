<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Exports;

use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Tables\AdminTableDefinitions;
use Illuminate\Support\Facades\DB;

final readonly class AdminPermissionsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(
        private PermissionCatalogRegistry $permissions,
        private EffectivePermissionChecker $checker,
        private ModuleActivationService $activation,
        private ModuleKeyResolver $moduleKeys,
    ) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::PERMISSIONS;
    }

    public function tableName(): string
    {
        return 'Admin permissions';
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
        return 'admin-permissions-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'displayName' => 'Display name',
            'name' => 'Technical name',
            'guard' => 'Guard',
            'description' => 'Description',
            'module' => 'Module',
            'teamScoped' => 'Team scoped',
            'moduleActivation' => 'Module state',
            'assigned' => 'Assigned',
            'effective' => 'Effective',
            'ineffectiveReason' => 'Ineffective reason',
            'createdAt' => 'Created at',
            'updatedAt' => 'Updated at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $databasePermissions = DB::table(AuthorizationDatabaseTable::PERMISSIONS)
            ->get(['id', 'public_id', 'name', 'display_name', 'guard_name', 'created_at', 'updated_at'])
            ->keyBy('name');
        $teamId = is_string($request->activeTeamPublicId) ? $this->teamId($request->activeTeamPublicId) : null;

        $rows = array_map(function ($permission) use ($databasePermissions, $request, $teamId): array {
            $decision = is_string($request->activeTeamPublicId)
                ? $this->checker->check(new EffectivePermissionRequest($request->requestingUserPublicId, $permission->name, $request->activeTeamPublicId))
                : null;
            $allowed = $decision !== null && $decision->allowed;
            $databasePermission = $databasePermissions->get($permission->name);
            $values = is_object($databasePermission) ? get_object_vars($databasePermission) : [];
            $id = $values['id'] ?? null;
            $publicId = $values['public_id'] ?? '';
            $displayName = $values['display_name'] ?? null;
            $guard = $values['guard_name'] ?? 'web';
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';
            $moduleKey = $this->moduleKeys->forPermission($permission->name);
            $moduleState = $teamId === null ? null : $this->activation->effectiveState($moduleKey, $teamId);

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
            if (self::filterValue($request, 'module') !== 'all' && $row['module'] !== self::filterValue($request, 'module')) {
                return false;
            }

            if (self::filterValue($request, 'activation') !== 'all' && $row['moduleActivation'] !== self::filterValue($request, 'activation')) {
                return false;
            }

            if (self::filterValue($request, 'teamScoped') !== 'all' && $row['teamScoped'] !== (self::filterValue($request, 'teamScoped') === 'yes')) {
                return false;
            }

            if (self::filterValue($request, 'assigned') !== 'all' && $row['assigned'] !== (self::filterValue($request, 'assigned') === 'yes')) {
                return false;
            }

            return self::filterValue($request, 'effective') === 'all' || $row['effective'] === (self::filterValue($request, 'effective') === 'yes');
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
