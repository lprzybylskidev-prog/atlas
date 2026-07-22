<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\AdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\DTOs\AuthorizationFingerprint;
use App\Modules\Core\Exports\Application\DTOs\ReportExportRequestSnapshot;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\AdminDataTableExportContext;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableState;
use Illuminate\Support\Facades\Config;

final class AdminDataTableExportSnapshotFactory
{
    public function snapshot(AdminDataTableExportProvider $provider, AdminDataTableExportContext $context, ReportExportFormat $format): ReportExportRequestSnapshot
    {
        $definition = $provider->tableDefinition();
        $safeState = $this->safeState($context->state, $definition);
        $allowedColumns = $this->safeColumnSet($provider->allowedExportColumns($context), $definition);
        $visibleColumns = $this->visibleColumns($safeState, $allowedColumns, $definition);

        return new ReportExportRequestSnapshot(
            reportKey: $provider->tableKey(),
            reportName: $provider->tableName(),
            moduleKey: $provider->owningModuleKey(),
            format: $format,
            activeTeamId: $context->activeTeamId,
            activeTeamPublicId: $context->activeTeamPublicId,
            requestingUserId: $context->requestingUserId,
            requestingUserPublicId: $context->requestingUserPublicId,
            filters: $context->filters + ['search' => $safeState->search],
            sorting: [['id' => $safeState->sort, 'desc' => $safeState->direction === 'desc']],
            visibleColumns: $visibleColumns,
            columnOrder: $this->orderedColumns($safeState, $visibleColumns, $definition),
            timeRange: $context->timeRange,
            authorization: new AuthorizationFingerprint(
                moduleKey: $provider->owningModuleKey(),
                activeTeamPublicId: $context->activeTeamPublicId,
                requestingUserPublicId: $context->requestingUserPublicId,
                permissionNames: [$provider->requestPermission()],
                allowedColumns: $allowedColumns,
                ruleVersion: $provider->ruleVersion(),
            ),
            releaseVersion: Config::string('atlas.release.version', '0.1.0-dev'),
            ruleVersion: $provider->ruleVersion(),
            expiresAt: $context->expiresAt,
            synchronousAllowed: $context->allowSynchronous,
            auditExport: $context->auditExport,
            estimatedRowCount: $context->estimatedRowCount,
        );
    }

    private function safeState(TableState $state, TableDefinition $definition): TableState
    {
        $sort = in_array($state->sort, $definition->sortableKeys(), true) ? $state->sort : $definition->defaultSort;

        return new TableState(
            page: $state->page,
            perPage: $state->perPage,
            sort: $sort,
            direction: $state->direction === 'desc' ? 'desc' : 'asc',
            search: mb_substr(trim(preg_replace('/[[:cntrl:]]/', '', $state->search) ?? ''), 0, 120),
            columns: $this->safeColumnSet($state->columns, $definition),
            columnOrder: $this->safeColumnSet($state->columnOrder, $definition),
            view: $state->view,
        );
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function safeColumnSet(array $columns, TableDefinition $definition): array
    {
        $safe = [];
        $allowed = $definition->columnKeys();

        foreach ($columns as $column) {
            if (in_array($column, $allowed, true) && ! in_array($column, $safe, true)) {
                $safe[] = $column;
            }
        }

        return $safe;
    }

    /**
     * @param  list<string>  $allowedColumns
     * @return list<string>
     */
    private function visibleColumns(TableState $state, array $allowedColumns, TableDefinition $definition): array
    {
        $requested = $state->columns === [] ? $definition->defaultVisibleColumns() : $state->columns;
        $visible = array_values(array_filter($requested, static fn (string $column): bool => in_array($column, $allowedColumns, true)));

        if ($visible !== []) {
            return $visible;
        }

        return array_values(array_filter(
            $definition->defaultVisibleColumns(),
            static fn (string $column): bool => in_array($column, $allowedColumns, true),
        ));
    }

    /**
     * @param  list<string>  $visibleColumns
     * @return list<string>
     */
    private function orderedColumns(TableState $state, array $visibleColumns, TableDefinition $definition): array
    {
        $order = $state->columnOrder === [] ? $definition->columnKeys() : $state->columnOrder;
        $ordered = array_values(array_filter($order, static fn (string $column): bool => in_array($column, $visibleColumns, true)));

        foreach ($visibleColumns as $column) {
            if (! in_array($column, $ordered, true)) {
                $ordered[] = $column;
            }
        }

        return $ordered;
    }
}
