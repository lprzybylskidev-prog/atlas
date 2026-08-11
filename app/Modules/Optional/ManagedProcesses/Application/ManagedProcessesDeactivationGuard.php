<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application;

use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Enums\ProcessRunStatus;
use App\Modules\Optional\ManagedProcesses\Infrastructure\Persistence\TableNames\ManagedProcessesDatabaseTable;
use App\Shared\Application\Modules\Contracts\ModuleDeactivationGuard;
use App\Shared\Application\Modules\ModuleDeactivationAssessment;
use App\Shared\Application\Modules\ModuleDeactivationBlocker;
use App\Shared\Application\Modules\ModuleDeactivationRequest;
use App\Shared\Application\Modules\ModuleDeactivationSafeAction;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

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

        $schedule = $this->database->table(ManagedProcessesDatabaseTable::SCHEDULES)
            ->where(static function (Builder $query) use ($blockingKeys, $request): void {
                $query->where('module_key', $request->moduleKey->value);

                if ($blockingKeys !== []) {
                    $query->orWhereIn('process_key', $blockingKeys);
                }
            })
            ->where('enabled', true)
            ->when($request->teamId !== null, static fn (Builder $query): Builder => $query->where('team_id', $request->teamId))
            ->orderByDesc('created_at')
            ->first();

        if (is_object($schedule)) {
            return ModuleDeactivationAssessment::block(
                new ModuleDeactivationBlocker(
                    processType: 'managed_process_schedule',
                    processIdentifier: $this->stringValue($schedule->public_id ?? null),
                    reason: sprintf('Enabled managed process schedule %s must be disabled before module deactivation.', $this->stringValue($schedule->public_id ?? null)),
                ),
                [new ModuleDeactivationSafeAction('managed_process.disable_schedule', 'Disable the managed process schedule before deactivation.')],
            );
        }

        $run = $this->database->table(ManagedProcessesDatabaseTable::RUNS)
            ->where(static function (Builder $query) use ($blockingKeys, $request): void {
                $query->where('module_key', $request->moduleKey->value);

                if ($blockingKeys !== []) {
                    $query->orWhereIn('process_key', $blockingKeys);
                }
            })
            ->whereIn('status', [
                ProcessRunStatus::Draft->value,
                ProcessRunStatus::Queued->value,
                ProcessRunStatus::Running->value,
                ProcessRunStatus::Waiting->value,
            ])
            ->when($request->teamId !== null, static fn (Builder $query): Builder => $query->where('team_id', $request->teamId))
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
