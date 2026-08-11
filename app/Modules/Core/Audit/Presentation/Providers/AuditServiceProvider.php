<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Presentation\Providers;

use App\Modules\Core\Audit\Application\Exports\AdminAuditEventsDataTableExportProvider;
use App\Modules\Core\Audit\Application\Exports\AdminImpersonationSessionEventsDataTableExportProvider;
use App\Modules\Core\Audit\Application\Exports\AdminSecurityHistoryDataTableExportProvider;
use App\Modules\Core\Audit\Application\Permissions\AuditPermissionCatalog;
use App\Modules\Core\Audit\Application\Public\Contracts\AuditEventLookup;
use App\Modules\Core\Audit\Infrastructure\Persistence\AuditSecurityAuditRecorder;
use App\Modules\Core\Audit\Infrastructure\Persistence\DatabaseAuditRecorder;
use App\Modules\Core\Audit\Infrastructure\Runtime\NullAuditActorContextProvider;
use App\Modules\Core\Audit\Presentation\Inertia\AuditRouteAvailability;
use App\Modules\Core\Identity\Application\Public\Contracts\SecurityAuditRecorder;
use App\Shared\Application\Audit\ConfiguredAuditCatalog;
use App\Shared\Application\Audit\Contracts\AuditActorContextProvider;
use App\Shared\Application\Audit\Contracts\AuditCatalog;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([AuditPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([AuditRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([
            AdminAuditEventsDataTableExportProvider::class,
            AdminImpersonationSessionEventsDataTableExportProvider::class,
            AdminSecurityHistoryDataTableExportProvider::class,
        ], 'atlas.admin_data_table_export_providers');

        $this->app->bindIf(AuditActorContextProvider::class, NullAuditActorContextProvider::class);
        $this->app->singleton(AuditCatalog::class, fn (): ConfiguredAuditCatalog => new ConfiguredAuditCatalog(
            $this->auditCatalogConfiguration(),
        ));

        $this->app->singleton(DatabaseAuditRecorder::class, function (): DatabaseAuditRecorder {
            return new DatabaseAuditRecorder(
                $this->app->make(ConnectionInterface::class),
                $this->app->make(AuditActorContextProvider::class),
                $this->app->make(AuditCatalog::class),
            );
        });
        $this->app->bind(AuditEventLookup::class, fn (): DatabaseAuditRecorder => $this->app->make(DatabaseAuditRecorder::class));
        $this->app->bind(AuditRecorder::class, fn (): DatabaseAuditRecorder => $this->app->make(DatabaseAuditRecorder::class));

        $this->app->bind(SecurityAuditRecorder::class, AuditSecurityAuditRecorder::class);
    }

    /**
     * @return array<string, array{actions: list<string>, sources: list<string>, target_types: list<string>, aggregate_types: list<string>, metadata_keys: list<string>, security_categories: list<string>}>
     */
    private function auditCatalogConfiguration(): array
    {
        $modules = config('audit.modules', []);

        if (! is_array($modules)) {
            return [];
        }

        /** @var array<string, array{actions: list<string>, sources: list<string>, target_types: list<string>, aggregate_types: list<string>, metadata_keys: list<string>, security_categories: list<string>}> $modules */
        return $modules;
    }
}
