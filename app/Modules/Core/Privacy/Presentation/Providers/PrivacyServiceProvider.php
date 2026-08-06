<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Presentation\Providers;

use App\Modules\Core\Privacy\Application\Permissions\PrivacyPermissionCatalog;
use App\Modules\Core\Privacy\Application\Services\DataLifecycleParticipantRegistry;
use App\Modules\Core\Privacy\Application\Services\PrivacyOperationExecutor;
use App\Modules\Core\Privacy\Application\Services\PrivacyOperationPreviewer;
use App\Modules\Core\Privacy\Application\Services\PrivacyRetentionCoverageCatalog;
use App\Modules\Core\Privacy\Presentation\Http\PrivacyHighRiskContinuation;
use App\Modules\Core\Privacy\Presentation\Inertia\PrivacyRouteAvailability;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use Illuminate\Support\ServiceProvider;

final class PrivacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([PrivacyPermissionCatalog::class], 'atlas.permission_catalogs');
        $this->app->tag([PrivacyRouteAvailability::class], 'atlas.inertia_route_availability');
        $this->app->tag([PrivacyHighRiskContinuation::class], 'atlas.high_risk_reauthentication_continuations');
        $this->app->singleton(DataLifecycleParticipantRegistry::class, fn (): DataLifecycleParticipantRegistry => new DataLifecycleParticipantRegistry(
            $this->dataLifecycleParticipants(),
        ));
        $this->app->singleton(PrivacyRetentionCoverageCatalog::class);
        $this->app->singleton(PrivacyOperationExecutor::class);
        $this->app->singleton(PrivacyOperationPreviewer::class);
    }

    /**
     * @return list<DataLifecycleParticipant>
     */
    private function dataLifecycleParticipants(): array
    {
        $participants = [];

        foreach ($this->app->tagged('atlas.data_lifecycle_participants') as $participant) {
            if ($participant instanceof DataLifecycleParticipant) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }
}
