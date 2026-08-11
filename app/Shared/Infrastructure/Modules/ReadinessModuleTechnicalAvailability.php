<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Modules;

use App\Modules\Core\Health\Application\Readiness\Contracts\ReadinessChecker;
use App\Modules\Core\Health\Application\Readiness\HealthCheckStatus;
use App\Modules\Core\Health\Application\Readiness\ReadinessCheckResult;
use App\Shared\Application\Modules\Contracts\ModuleDefinition;
use App\Shared\Application\Modules\Contracts\ModuleTechnicalAvailability;

final readonly class ReadinessModuleTechnicalAvailability implements ModuleTechnicalAvailability
{
    public function __construct(private ReadinessChecker $readiness) {}

    public function available(ModuleDefinition $module): bool
    {
        $healthChecks = $module->healthChecks();

        if ($healthChecks === []) {
            return true;
        }

        $checks = $this->checksByKey();

        foreach ($healthChecks as $healthCheck) {
            $result = $checks[$healthCheck] ?? null;

            if (! $result instanceof ReadinessCheckResult || $result->status === HealthCheckStatus::Unhealthy) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, ReadinessCheckResult>
     */
    private function checksByKey(): array
    {
        $checks = [];

        foreach ($this->readiness->check()->checks as $check) {
            $checks[$check->key] = $check;
        }

        return $checks;
    }
}
