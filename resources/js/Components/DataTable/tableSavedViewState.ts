import type { SortingState, VisibilityState } from '@tanstack/vue-table';
import type { DataTableColumn, DataTableMeta, DataTableSavedView } from '../../Types/data-table';
import { compactTableFilters, type TableQueryPrimitive } from './tableQueryState';

export interface SavedViewStatePayload {
    [key: string]: string | string[] | Record<string, string | number | boolean | null> | null | undefined;
    sort: string;
    direction: 'asc' | 'desc';
    search: string;
    columns: string[];
    columnOrder: string[];
    filters?: Record<string, string | number | boolean | null>;
    grouping?: string[];
    timeRange?: DataTableMeta['state']['timeRange'];
}

export function buildSavedViewState<TRow extends Record<string, unknown>>(input: {
    sorting: SortingState;
    search: string;
    visibility: VisibilityState;
    columns: DataTableColumn<TRow>[];
    orderedColumnKeys: string[];
    serverState?: DataTableMeta['state'];
    filters?: Record<string, TableQueryPrimitive>;
}): SavedViewStatePayload {
    const currentSort = input.sorting[0];
    return {
        sort: currentSort?.id ?? input.serverState?.sort ?? input.columns[0]?.key ?? '',
        direction: currentSort?.desc ? 'desc' : 'asc',
        search: input.search,
        columns: input.columns.filter((column) => input.visibility[column.key] ?? true).map((column) => column.key),
        columnOrder: input.orderedColumnKeys,
        filters: compactTableFilters(input.filters),
        grouping: input.serverState?.grouping ?? [],
        timeRange: input.serverState?.timeRange ?? null,
    };
}

export function findSavedView(views: DataTableSavedView[] = [], publicId = ''): DataTableSavedView | undefined {
    return views.find((view) => view.publicId === publicId);
}

export function rememberSavedView(storage: Storage | undefined, key: string | null, viewId: string): void {
    if (storage === undefined || key === null) return;
    if (viewId === '') storage.removeItem(key);
    else storage.setItem(key, viewId);
}
