import type { VisibilityState } from '@tanstack/vue-table';
import type { DataTableColumn } from '../../Types/data-table';

export function allowedTableColumns<TRow extends Record<string, unknown>>(columns: DataTableColumn<TRow>[]): DataTableColumn<TRow>[] {
    return columns.filter((column) => column.access !== 'forbidden');
}

export function defaultTableColumnVisibility<TRow extends Record<string, unknown>>(columns: DataTableColumn<TRow>[]): VisibilityState {
    return Object.fromEntries(columns.map((column) => [column.key, column.visibility !== 'hidden' && column.hidden !== true]));
}

export function visibilityFromTableColumnKeys<TRow extends Record<string, unknown>>(
    columns: DataTableColumn<TRow>[],
    visibleKeys: string[],
): VisibilityState {
    return Object.fromEntries(columns.map((column) => [column.key, visibleKeys.includes(column.key)]));
}

export function normalizeTableColumnVisibility<TRow extends Record<string, unknown>>(
    columns: DataTableColumn<TRow>[],
    persisted?: VisibilityState,
): VisibilityState {
    const defaults = defaultTableColumnVisibility(columns);
    if (persisted === undefined) return defaults;

    return Object.fromEntries(columns.map((column) => [column.key, persisted[column.key] ?? defaults[column.key] ?? true]));
}

export function orderTableColumns<TRow extends Record<string, unknown>>(
    columns: DataTableColumn<TRow>[],
    order: string[],
): DataTableColumn<TRow>[] {
    if (order.length === 0) return columns;
    const rank = new Map(order.map((key, index) => [key, index]));
    return [...columns].sort(
        (first, second) => (rank.get(first.key) ?? Number.MAX_SAFE_INTEGER) - (rank.get(second.key) ?? Number.MAX_SAFE_INTEGER),
    );
}

export function dataTableColumnWidthClass<TRow extends Record<string, unknown>>(
    columns: DataTableColumn<TRow>[],
    columnId: string,
): string {
    const column = columns.find((candidate) => candidate.key === columnId);
    if (column === undefined) return 'min-w-44';
    if (column.key.toLowerCase().includes('email')) return 'min-w-56';
    if (column.key.toLowerCase().includes('publicid') || column.key.toLowerCase().includes('public_id')) return 'min-w-36';
    if (column.format === 'boolean' || column.format === 'count' || column.format === 'number' || column.format === 'percent')
        return 'min-w-28';
    if (column.format === 'date' || column.format === 'time' || column.format === 'datetime') return 'min-w-40';
    if (column.format === 'money' || column.format === 'file-size') return 'min-w-36';
    if (column.format === 'list') return 'min-w-56';
    return 'min-w-44';
}
