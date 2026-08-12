import type { DataTableColumn, DataTableMeta } from '../../Types/data-table';
import type { PaginationState, SortingState, VisibilityState } from '@tanstack/vue-table';

export type TableQueryPrimitive = string | number | boolean | null | undefined;

const ownedQueryKeys = new Set(['page', 'per_page', 'sort', 'direction', 'search', 'columns', 'column_order', 'view']);

export function compactTableFilters(filters?: Record<string, TableQueryPrimitive>): Record<string, string | number> {
    if (filters === undefined) return {};

    return Object.fromEntries(
        Object.entries(filters)
            .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
            .map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : (value as string | number)]),
    );
}

export function appliedTableFilters(
    tableFilters: Record<string, TableQueryPrimitive> = {},
    externalFilters: Record<string, TableQueryPrimitive> = {},
    search = '',
): Record<string, TableQueryPrimitive> {
    const filters = { ...tableFilters, ...externalFilters };

    for (const [key, value] of new URLSearchParams(search).entries()) {
        if (!ownedQueryKeys.has(key)) filters[key] = value;
    }

    return filters;
}

export function tableQueryPayload<TRow extends Record<string, unknown>>(input: {
    pagination: PaginationState;
    sorting: SortingState;
    search: string;
    visibility: VisibilityState;
    columns: DataTableColumn<TRow>[];
    orderedColumnKeys: string[];
    selectedViewId: string;
    serverState?: DataTableMeta['state'];
    filters?: Record<string, TableQueryPrimitive>;
}): Record<string, string | number> {
    const currentSort = input.sorting[0];
    const visibleColumns = input.columns.filter((column) => input.visibility[column.key] ?? true).map((column) => column.key);

    return {
        page: input.pagination.pageIndex + 1,
        per_page: input.pagination.pageSize,
        sort: currentSort?.id ?? input.serverState?.sort ?? input.columns[0]?.key ?? '',
        direction: currentSort?.desc ? 'desc' : 'asc',
        search: input.search,
        columns: visibleColumns.join(','),
        column_order: input.orderedColumnKeys.join(','),
        view: input.selectedViewId,
        ...compactTableFilters(input.filters),
    };
}
