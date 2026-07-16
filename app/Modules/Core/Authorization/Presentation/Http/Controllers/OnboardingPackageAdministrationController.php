<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use App\Modules\Core\Authorization\Application\Packages\OnboardingPackageCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
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
        [$userId, $teamId] = $this->context->userTeam($request);
        $databasePackages = DB::table('authorization_onboarding_packages')
            ->get(['id', 'public_id', 'name', 'is_active', 'created_at', 'updated_at'])
            ->keyBy('name');
        $rows = array_map(static function ($package) use ($databasePackages): array {
            $databasePackage = $databasePackages->get($package->name);
            $values = is_object($databasePackage) ? get_object_vars($databasePackage) : [];
            $id = $values['id'] ?? null;
            $isActive = $values['is_active'] ?? true;
            $createdAt = $values['created_at'] ?? '';
            $updatedAt = $values['updated_at'] ?? '';

            return [
                'id' => is_numeric($id) ? (int) $id : null,
                'publicId' => $package->publicId,
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
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Authorization/Packages', [
            'packages' => $result->rows,
            'table' => $result->tableMeta($definition->key),
        ]);
    }
}
