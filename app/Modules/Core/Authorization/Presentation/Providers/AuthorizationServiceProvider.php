<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Providers;

use App\Modules\Core\Authorization\Application\Contracts\OnboardingPackageStore;
use App\Modules\Core\Authorization\Application\Contracts\PermissionRoleStore;
use App\Modules\Core\Authorization\Application\Exports\AdminOnboardingPackagesDataTableExportProvider;
use App\Modules\Core\Authorization\Application\Exports\AdminPermissionsDataTableExportProvider;
use App\Modules\Core\Authorization\Application\Exports\AdminRolesDataTableExportProvider;
use App\Modules\Core\Authorization\Application\Packages\PublicOnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Packages\PublicUserAuthorizationAssignmentCopier;
use App\Modules\Core\Authorization\Application\Packages\PublicUserOnboardingPackageApplier;
use App\Modules\Core\Authorization\Application\Permissions\CoreAuthorizationPermissionCatalog;
use App\Modules\Core\Authorization\Application\Permissions\PermissionCatalogRegistry;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\Contracts\OnboardingPackageDirectory;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentCopier;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserAuthorizationAssignmentPreviewer;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserOnboardingPackageApplier;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationCleaner;
use App\Modules\Core\Authorization\Application\Public\Contracts\UserTeamAuthorizationManager;
use App\Modules\Core\Authorization\Application\Roles\AdministratorAccess;
use App\Modules\Core\Authorization\Infrastructure\Persistence\DatabaseOnboardingPackageStore;
use App\Modules\Core\Authorization\Infrastructure\Persistence\SpatieEffectivePermissionChecker;
use App\Modules\Core\Authorization\Infrastructure\Persistence\SpatiePermissionRoleStore;
use App\Modules\Core\Authorization\Infrastructure\Persistence\SpatiePublicIdHooks;
use App\Modules\Core\Authorization\Infrastructure\Persistence\SpatieUserAuthorizationAssignmentPreviewer;
use App\Modules\Core\Authorization\Presentation\Console\UpdateAdministratorRolePermissions;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Exports\AdminModuleDetailHistoryDataTableExportProvider;
use App\Shared\Application\Modules\Exports\AdminModuleDetailSchedulesDataTableExportProvider;
use App\Shared\Application\Modules\Exports\AdminModuleDetailTeamsDataTableExportProvider;
use App\Shared\Application\Modules\Exports\AdminModulesDataTableExportProvider;
use App\Shared\Infrastructure\Observability\Exports\AdminApplicationLogsDataTableExportProvider;
use App\Shared\Infrastructure\Queues\Exports\AdminFailedJobsDataTableExportProvider;
use Illuminate\Support\ServiceProvider;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([CoreAuthorizationPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->bind(EffectivePermissionChecker::class, SpatieEffectivePermissionChecker::class);
        $this->app->bind(AdministratorAccessManager::class, AdministratorAccess::class);
        $this->app->bind(OnboardingPackageDirectory::class, PublicOnboardingPackageDirectory::class);
        $this->app->bind(UserOnboardingPackageApplier::class, PublicUserOnboardingPackageApplier::class);
        $this->app->bind(UserAuthorizationAssignmentCopier::class, PublicUserAuthorizationAssignmentCopier::class);
        $this->app->bind(UserAuthorizationAssignmentPreviewer::class, SpatieUserAuthorizationAssignmentPreviewer::class);
        $this->app->bind(UserTeamAuthorizationCleaner::class, SpatiePermissionRoleStore::class);
        $this->app->bind(UserTeamAuthorizationManager::class, SpatiePermissionRoleStore::class);
        $this->app->bind(OnboardingPackageStore::class, DatabaseOnboardingPackageStore::class);
        $this->app->bind(PermissionRoleStore::class, SpatiePermissionRoleStore::class);
        $this->app->tag([
            AdminApplicationLogsDataTableExportProvider::class,
            AdminFailedJobsDataTableExportProvider::class,
            AdminModuleDetailHistoryDataTableExportProvider::class,
            AdminModuleDetailSchedulesDataTableExportProvider::class,
            AdminModuleDetailTeamsDataTableExportProvider::class,
            AdminModulesDataTableExportProvider::class,
            AdminOnboardingPackagesDataTableExportProvider::class,
            AdminPermissionsDataTableExportProvider::class,
            AdminRolesDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');

        $this->app->singleton(PermissionCatalogRegistry::class, fn (): PermissionCatalogRegistry => new PermissionCatalogRegistry(
            $this->permissionCatalogs(),
        ));
    }

    public function boot(): void
    {
        SpatiePublicIdHooks::register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateAdministratorRolePermissions::class,
            ]);
        }
    }

    /**
     * @return list<ModulePermissionContribution>
     */
    private function permissionCatalogs(): array
    {
        $catalogs = [];

        foreach ($this->app->tagged('atlas.permission_catalogs') as $catalog) {
            if ($catalog instanceof ModulePermissionContribution) {
                $catalogs[] = $catalog;
            }
        }

        return $catalogs;
    }
}
