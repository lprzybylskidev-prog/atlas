import { describe, expect, it } from 'vitest';
import { computed, ref } from 'vue';

import type { DataTableColumn, DataTableMeta } from '../../Types/data-table';
import {
    allowedTableColumns,
    dataTableColumnWidthClass,
    normalizeTableColumnVisibility,
    orderTableColumns,
    visibilityFromTableColumnKeys,
} from './tableColumnState';
import { readTableLocalState, tableStateStorageKey, writeTableLocalState } from './tableLocalState';
import { initialTablePagination, initialTableSorting, tablePageCount, tablePageSelectOptions } from './tablePaginationState';
import { appliedTableFilters, compactTableFilters, tableQueryPayload } from './tableQueryState';
import { buildSavedViewState, findSavedView, rememberSavedView } from './tableSavedViewState';
import { selectedTableRowIds, selectEveryTableRow } from './tableSelectionState';
import { useDataTableActions } from './useDataTableActions';
import { useDataTableSavedViews } from './useDataTableSavedViews';

interface Row extends Record<string, unknown> {
    name: string;
    email: string;
    secret: string;
}

const columns: DataTableColumn<Row>[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email', visibility: 'hidden' },
    { key: 'secret', label: 'Secret', access: 'forbidden' },
];

const serverState: DataTableMeta['state'] = {
    page: 3,
    perPage: 25,
    sort: 'name',
    direction: 'desc',
    search: 'atlas',
    columns: ['name'],
    columnOrder: ['email', 'name'],
    filters: { active: true },
    grouping: ['team'],
    timeRange: null,
    view: 'view-1',
};

function memoryStorage(): Storage {
    const values = new Map<string, string>();
    return {
        get length() {
            return values.size;
        },
        clear: () => values.clear(),
        getItem: (key) => values.get(key) ?? null,
        key: (index) => [...values.keys()][index] ?? null,
        removeItem: (key) => {
            values.delete(key);
        },
        setItem: (key, value) => {
            values.set(key, value);
        },
    };
}

describe('DataTable focused state units', () => {
    it('owns allowed columns, order, visibility, and width adaptation outside the host', () => {
        const allowed = allowedTableColumns(columns);
        expect(allowed.map(({ key }) => key)).toEqual(['name', 'email']);
        expect(orderTableColumns(allowed, ['email', 'name']).map(({ key }) => key)).toEqual(['email', 'name']);
        expect(normalizeTableColumnVisibility(allowed)).toEqual({ name: true, email: false });
        expect(visibilityFromTableColumnKeys(allowed, ['email'])).toEqual({ name: false, email: true });
        expect(dataTableColumnWidthClass(allowed, 'email')).toBe('min-w-56');
    });

    it('adapts server sorting and pagination deterministically', () => {
        expect(initialTableSorting(serverState)).toEqual([{ id: 'name', desc: true }]);
        expect(initialTablePagination(serverState)).toEqual({ pageIndex: 2, pageSize: 25 });
        expect(tablePageCount(51, 25)).toBe(3);
        expect(tablePageSelectOptions(2)).toEqual([
            { value: 0, label: '1' },
            { value: 1, label: '2' },
        ]);
    });

    it('keeps applied filters and query serialization separate from draft state', () => {
        expect(appliedTableFilters({ active: true }, { category: 'legal' }, '?page=8&team=alpha')).toEqual({
            active: true,
            category: 'legal',
            team: 'alpha',
        });
        expect(compactTableFilters({ active: true, empty: '', absent: null })).toEqual({ active: 1 });
        expect(
            tableQueryPayload({
                pagination: { pageIndex: 1, pageSize: 25 },
                sorting: [{ id: 'email', desc: false }],
                search: 'anna',
                visibility: { name: true, email: false },
                columns: allowedTableColumns(columns),
                orderedColumnKeys: ['email', 'name'],
                selectedViewId: 'view-1',
                serverState,
                filters: { active: false },
            }),
        ).toEqual({
            page: 2,
            per_page: 25,
            sort: 'email',
            direction: 'asc',
            search: 'anna',
            columns: 'name',
            column_order: 'email,name',
            view: 'view-1',
            active: 0,
        });
    });

    it('owns current-page selection without retaining false entries', () => {
        const selection = selectEveryTableRow(['a', 'b']);
        expect(selection).toEqual({ a: true, b: true });
        expect(selectedTableRowIds({ ...selection, c: false })).toEqual(['a', 'b']);
    });

    it('owns row-action availability and disabled explanations', () => {
        const actions = useDataTableActions<Row>({
            actions: () => [
                { key: 'open', label: 'Open', available: true },
                { key: 'hidden', label: 'Hidden', available: false },
                { key: 'blocked', label: 'Blocked', disabled: true, disabledReason: 'Not permitted' },
            ],
            selectedRowIds: computed(() => []),
            emitBulkAction: () => undefined,
        });
        const row = { name: 'Anna', email: 'a@example.test', secret: 'x' };
        expect(actions.visibleActions(row).map(({ key }) => key)).toEqual(['open', 'blocked']);
        expect(actions.actionTooltip(actions.visibleActions(row)[1], row)).toBe('Blocked: Not permitted');
    });

    it('executes callback row actions and queued bulk actions through the focused action unit', async () => {
        const rowCalls: string[] = [];
        const bulkCalls: string[][] = [];
        const emitted: string[][] = [];
        const actions = useDataTableActions<Row>({
            actions: () => [],
            selectedRowIds: computed(() => ['a', 'b']),
            bulkActionHandler: ({ rowIds }) => {
                bulkCalls.push(rowIds);
            },
            emitBulkAction: ({ rowIds }) => {
                emitted.push(rowIds);
            },
        });
        const row = { name: 'Anna', email: 'a@example.test', secret: 'x' };
        await actions.runRowAction(
            {
                key: 'open',
                label: 'Open',
                onAction: (current) => {
                    rowCalls.push(current.name);
                },
            },
            row,
        );
        actions.runBulkAction({ key: 'archive', label: 'Archive', execution: 'queued' });
        await Promise.resolve();
        await Promise.resolve();
        expect(rowCalls).toEqual(['Anna']);
        expect(bulkCalls).toEqual([['a', 'b']]);
        expect(emitted).toEqual([['a', 'b']]);
    });

    it('reads, writes, and repairs persisted local table state', () => {
        const storage = memoryStorage();
        const key = tableStateStorageKey('users');
        writeTableLocalState(storage, key, { globalFilter: 'anna', pagination: { pageIndex: 2, pageSize: 50 } });
        expect(readTableLocalState(storage, key)).toEqual({ globalFilter: 'anna', pagination: { pageIndex: 2, pageSize: 50 } });
        storage.setItem(key ?? '', '{broken');
        expect(readTableLocalState(storage, key)).toEqual({});
        expect(storage.getItem(key ?? '')).toBeNull();
    });

    it('builds, resolves, and remembers saved-view state', () => {
        const state = buildSavedViewState({
            sorting: [{ id: 'name', desc: true }],
            search: 'atlas',
            visibility: { name: true, email: false },
            columns: allowedTableColumns(columns),
            orderedColumnKeys: ['email', 'name'],
            serverState,
            filters: { active: true },
        });
        expect(state).toMatchObject({ sort: 'name', direction: 'desc', search: 'atlas', columns: ['name'], filters: { active: 1 } });
        const view = { publicId: 'view-1', name: 'Mine', type: 'private' as const, state, isDefault: false };
        expect(findSavedView([view], 'view-1')).toBe(view);
        const storage = memoryStorage();
        rememberSavedView(storage, 'selected', 'view-1');
        expect(storage.getItem('selected')).toBe('view-1');
        rememberSavedView(storage, 'selected', '');
        expect(storage.getItem('selected')).toBeNull();
    });

    it('resolves the selected view through focused saved-view orchestration', () => {
        const view = { publicId: 'view-1', name: 'Mine', type: 'private' as const, state: {}, isDefault: false };
        const savedViews = useDataTableSavedViews({
            table: () => ({
                key: 'users',
                state: serverState,
                pagination: { total: 1, page: 1, perPage: 10, from: 1, to: 1 },
                savedViews: [view],
            }),
            fallbackSort: () => 'name',
            pagination: ref({ pageIndex: 0, pageSize: 10 }),
            selectedViewId: ref('view-1'),
            selectedViewStorageKey: ref('selected'),
            savedViewName: ref(''),
            savedViewType: ref<'private' | 'team'>('private'),
            buildState: () => buildSavedViewState({ sorting: [], search: '', visibility: {}, columns: [], orderedColumnKeys: [] }),
            applyLocally: () => undefined,
            scheduleServerSync: () => undefined,
            copyName: (name) => `Copy ${name}`,
        });
        expect(savedViews.selectedView()).toBe(view);
    });
});
