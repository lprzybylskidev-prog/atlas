<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Optional\ManagedProcesses\Application\Contracts\ProcessDefinitionRegistry;
use App\Modules\Optional\ManagedProcesses\Application\Public\DTOs\ProcessDefinition;
use App\Shared\Application\Tables\AdminTableDefinitions;

final readonly class AdminManagedProcessDefinitionsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function __construct(private ProcessDefinitionRegistry $definitions) {}

    public function tableKey(): string
    {
        return AdminTableDefinitions::MANAGED_PROCESS_DEFINITIONS;
    }

    public function tableName(): string
    {
        return 'Managed process definitions';
    }

    public function owningModuleKey(): string
    {
        return 'managed_processes';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-managed-process-definitions-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'label' => 'Process',
            'key' => 'Key',
            'moduleKey' => 'Module',
            'scope' => 'Scope',
            'queueName' => 'Queue',
            'executionMode' => 'Mode',
            'concurrencyPolicy' => 'Concurrency',
            'retryable' => 'Retry',
            'scheduleSupported' => 'Schedule',
            'manualStartSupported' => 'Manual start',
            'cancellationPolicy' => 'Cancellation',
            'externalEffects' => 'External effects',
            'highRisk' => 'High risk',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $rows = array_map(static fn (ProcessDefinition $definition): array => [
            'key' => $definition->key,
            'moduleKey' => $definition->moduleKey,
            'label' => $definition->label,
            'scope' => $definition->scope,
            'queueName' => $definition->queueName,
            'executionMode' => $definition->executionMode,
            'concurrencyPolicy' => $definition->concurrencyPolicy,
            'retryable' => $definition->retryPolicy->retryable,
            'scheduleSupported' => $definition->scheduleSupported,
            'manualStartSupported' => $definition->manualStartSupported,
            'cancellationPolicy' => $definition->cancellationPolicy,
            'externalEffects' => $definition->externalEffects,
            'highRisk' => $definition->highRisk,
        ], $this->definitions->all());

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
            $module = self::filterValue($request, 'module');

            if ($module !== '' && $module !== 'all' && $row['moduleKey'] !== $module) {
                return false;
            }

            foreach (['scheduleSupported' => 'schedule', 'manualStartSupported' => 'manual'] as $column => $filter) {
                $value = self::filterValue($request, $filter);

                if ($value !== '' && $value !== 'all' && $row[$column] !== ($value === 'yes')) {
                    return false;
                }
            }

            return true;
        }));
    }
}
