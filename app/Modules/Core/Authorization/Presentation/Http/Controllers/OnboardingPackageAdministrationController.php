<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
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

final readonly class OnboardingPackageAdministrationController
{
    public function __construct(
        private OnboardingPackageCatalog $packages,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::PACKAGES);
        $state = TableState::fromRequest($request, $definition);
        $filters = $this->filters($request);
        [$userId, $teamId] = $this->context->userTeam($request);
        $databasePackages = DB::table(AuthorizationDatabaseTable::AUTHORIZATION_ONBOARDING_PACKAGES)
            ->get(['id', 'public_id', 'is_active', 'created_at', 'updated_at'])
            ->keyBy('public_id');
        $rows = array_map(static function ($package) use ($databasePackages): array {
            $databasePackage = $databasePackages->get($package->publicId);
            $values = is_object($databasePackage) ? get_object_vars($databasePackage) : [];
            $id = $values['id'] ?? null;
            $isActive = $values['is_active'] ?? true;
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';

            return [
                'id' => is_numeric($id) ? (int) $id : null,
                'publicId' => $package->publicId,
                'teamPublicId' => $package->teamPublicId,
                'teamName' => $package->teamName,
                'name' => $package->name,
                'label' => $package->label,
                'initialRoles' => $package->initialRoleNames,
                'directPermissions' => $package->directPermissionNames,
                'templatePermissions' => $package->templatePermissionNames,
                'isActive' => (bool) $isActive,
                'createdAt' => is_string($createdAt) ? $createdAt : '',
                'updatedAt' => is_string($updatedAt) ? $updatedAt : '',
            ];
        }, $this->packages->all());
        $result = $this->tables->process($this->applyFilters($rows, $filters), $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
        $table = $result->tableMeta($definition->key, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/Authorization/Packages', [
            'packages' => $result->rows,
            'filterOptions' => [
                'teams' => $this->teamOptions($rows),
            ],
            'table' => $table,
        ]);
    }

    /**
     * @return array{status: string, team: string, roles: string, directPermissions: string, templatePermissions: string}
     */
    private function filters(Request $request): array
    {
        $status = $request->query('status');
        $team = $request->query('team');
        $roles = $request->query('roles');
        $directPermissions = $request->query('directPermissions');
        $templatePermissions = $request->query('templatePermissions');

        return [
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'all',
            'team' => is_string($team) && $team !== '' ? mb_substr($team, 0, 64) : 'all',
            'roles' => in_array($roles, ['with', 'without'], true) ? $roles : 'all',
            'directPermissions' => in_array($directPermissions, ['with', 'without'], true) ? $directPermissions : 'all',
            'templatePermissions' => in_array($templatePermissions, ['with', 'without'], true) ? $templatePermissions : 'all',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{status: string, team: string, roles: string, directPermissions: string, templatePermissions: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'active' && ($row['isActive'] ?? false) !== true) {
                return false;
            }

            if ($filters['status'] === 'inactive' && ($row['isActive'] ?? false) === true) {
                return false;
            }

            if ($filters['team'] !== 'all' && ($row['teamPublicId'] ?? null) !== $filters['team']) {
                return false;
            }

            $initialRoles = $row['initialRoles'] ?? [];
            $directPermissions = $row['directPermissions'] ?? [];
            $templatePermissions = $row['templatePermissions'] ?? [];
            $initialRolesCount = is_array($initialRoles) ? count($initialRoles) : 0;
            $directPermissionsCount = is_array($directPermissions) ? count($directPermissions) : 0;
            $templatePermissionsCount = is_array($templatePermissions) ? count($templatePermissions) : 0;

            if ($filters['roles'] === 'with' && $initialRolesCount <= 0) {
                return false;
            }

            if ($filters['roles'] === 'without' && $initialRolesCount > 0) {
                return false;
            }

            if ($filters['directPermissions'] === 'with' && $directPermissionsCount <= 0) {
                return false;
            }

            if ($filters['directPermissions'] === 'without' && $directPermissionsCount > 0) {
                return false;
            }

            if ($filters['templatePermissions'] === 'with' && $templatePermissionsCount <= 0) {
                return false;
            }

            if ($filters['templatePermissions'] === 'without' && $templatePermissionsCount > 0) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{value: string, label: string}>
     */
    private function teamOptions(array $rows): array
    {
        $options = [];

        foreach ($rows as $row) {
            $publicId = $row['teamPublicId'] ?? '';

            if (! is_string($publicId) || $publicId === '' || array_key_exists($publicId, $options)) {
                continue;
            }

            $name = $row['teamName'] ?? $publicId;
            $options[$publicId] = [
                'value' => $publicId,
                'label' => is_string($name) && $name !== '' ? $name : $publicId,
            ];
        }

        uasort($options, static fn (array $first, array $second): int => strcasecmp($first['label'], $second['label']));

        return array_values($options);
    }
}
