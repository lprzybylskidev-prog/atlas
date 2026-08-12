import type { DataTableMeta } from '../../Types/data-table';
import type { PaginationState, SortingState } from '@tanstack/vue-table';

export const tablePageSizes = [10, 25, 50, 100, 250] as const;

export function initialTableSorting(state?: DataTableMeta['state'], persisted: SortingState = []): SortingState {
    return state === undefined ? persisted : [{ id: state.sort, desc: state.direction === 'desc' }];
}

export function initialTablePagination(state?: DataTableMeta['state'], persisted?: PaginationState): PaginationState {
    return state === undefined
        ? (persisted ?? { pageIndex: 0, pageSize: 10 })
        : { pageIndex: Math.max(0, state.page - 1), pageSize: state.perPage };
}

export function tablePageCount(total: number, pageSize: number): number {
    return Math.max(1, Math.ceil(total / (pageSize || 10)));
}

export function tablePageSelectOptions(pageCount: number): Array<{ value: number; label: string }> {
    return Array.from({ length: Math.max(1, pageCount) }, (_, value) => ({ value, label: String(value + 1) }));
}
