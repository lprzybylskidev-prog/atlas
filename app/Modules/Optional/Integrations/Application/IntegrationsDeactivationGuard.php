<?php

declare(strict_types=1);

namespace App\Modules\Optional\Integrations\Application;

use App\Modules\Optional\Integrations\Application\Enums\IntegrationCircuitState;
use App\Modules\Optional\Integrations\Application\Public\Persistence\IntegrationsDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;
use Illuminate\Database\ConnectionInterface;

final readonly class IntegrationsDeactivationGuard implements ModuleDeactivationGuard
{
    public function __construct(private ConnectionInterface $db) {}

    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment
    {
        if ($request->moduleKey->value !== 'integrations') {
            return ModuleDeactivationAssessment::allow();
        }

        $running = $this->db->table(IntegrationsDatabaseTable::SYNC_RUNS)->where('status', 'running')->count();

        if ($running > 0) {
            return ModuleDeactivationAssessment::block(
                new ModuleDeactivationBlocker('integration_sync', 'running', 'Integration synchronization runs are still in progress.'),
                [new ModuleDeactivationSafeAction('wait_for_sync_runs', 'Wait until running integration synchronizations finish.')],
            );
        }

        $openCircuits = $this->db->table(IntegrationsDatabaseTable::CIRCUIT_BREAKERS)->where('state', IntegrationCircuitState::Open->value)->count();

        if ($openCircuits > 0) {
            return ModuleDeactivationAssessment::block(
                new ModuleDeactivationBlocker('integration_circuit', 'open', 'One or more integration circuit breakers are open and need operational review.'),
                [new ModuleDeactivationSafeAction('review_integrations', 'Review integration failures before deactivation.')],
            );
        }

        return ModuleDeactivationAssessment::allow();
    }
}
