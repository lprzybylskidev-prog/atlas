<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class ManagedProcessesDeactivationGuard implements ModuleDeactivationGuard
{
    public function __construct(
        private ProcessDefinitionRegistry $definitions,
        private ConnectionInterface $database,
    ) {}

    public function assess(ModuleDeactivationRequest $request): ModuleDeactivationAssessment
    {
        $blockingKeys = array_map(
            static fn ($definition): string => $definition->key,
            array_filter($this->definitions->all(), static fn ($definition): bool => $definition->moduleKey === $request->moduleKey->value && $definition->blocksModuleDeactivation),
        );

        if ($blockingKeys === []) {
            return ModuleDeactivationAssessment::allow();
        }

        $run = $this->database->table(DatabaseTable::MANAGED_PROCESS_RUNS)
            ->whereIn('process_key', $blockingKeys)
            ->whereIn('status', [
                ProcessRunStatus::Draft->value,
                ProcessRunStatus::Queued->value,
                ProcessRunStatus::Running->value,
                ProcessRunStatus::Waiting->value,
            ])
            ->orderByDesc('created_at')
            ->first();

        if (! is_object($run)) {
            return ModuleDeactivationAssessment::allow();
        }

        return ModuleDeactivationAssessment::block(
            new ModuleDeactivationBlocker(
                processType: $this->stringValue($run->process_key ?? null),
                processIdentifier: $this->stringValue($run->public_id ?? null),
                reason: sprintf('Managed process %s is %s.', $this->stringValue($run->public_id ?? null), $this->stringValue($run->status ?? null)),
            ),
            [new ModuleDeactivationSafeAction('managed_process.review', 'Wait for completion or cancel the run from Admin managed processes before deactivation.')],
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : 'unknown';
    }
}
