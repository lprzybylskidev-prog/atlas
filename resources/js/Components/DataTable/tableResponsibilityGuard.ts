export interface DataTableResponsibilityViolation {
    responsibility: string;
    pattern: string;
}

const centralOwnershipPatterns: Array<[string, RegExp]> = [
    ['query/applied state', /function\s+(?:currentServerState|currentFilterState|queryFilters|scheduleServerSync)\b/],
    ['sorting/pagination', /function\s+(?:initialSorting|serverColumnVisibility)\b|const\s+pageSizeOptions\s*=/],
    ['column visibility/order', /function\s+(?:normalizeColumnVisibility|visibilityFromColumnKeys|dataColumnWidthClass)\b/],
    ['selection', /function\s+(?:selectAllFiltered|clearSelection)\b/],
    ['persisted local state', /function\s+(?:readPersistedState|persistState|stateStorageKey)\b/],
    ['saved views', /function\s+(?:savedViewState|applySavedView|saveView|updateView|deleteView|copyView|makeDefaultView)\b/],
    ['row/bulk actions', /function\s+(?:runRowAction|runBulkAction|confirmBulkAction|actionTooltip)\b/],
    ['formatting', /function\s+(?:cellTooltipText|bodyCellContentClass|headerCellClass)\b/],
];

const requiredUnits = ['useDataTableController', 'DataTableSavedViewsMenu', 'DataTablePagination', 'DataTableStateRow'] as const;

export function dataTableResponsibilityViolations(source: string): DataTableResponsibilityViolation[] {
    const violations = centralOwnershipPatterns
        .filter(([, pattern]) => pattern.test(source))
        .map(([responsibility, pattern]) => ({ responsibility, pattern: pattern.source }));

    for (const unit of requiredUnits) {
        if (!source.includes(unit)) violations.push({ responsibility: `missing focused unit: ${unit}`, pattern: unit });
    }

    return violations;
}
