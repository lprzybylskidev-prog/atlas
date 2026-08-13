import { router } from '@inertiajs/vue3';
import {
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
    type ColumnDef,
    type VisibilityState,
} from '@tanstack/vue-table';
import { computed, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useTranslator } from '../../Localization/translator';
import { createDataTableFormatting } from '../../Services/dataTableFormatting';
import type {
    DataTableAction,
    DataTableBulkAction,
    DataTableColumn,
    DataTableExportMeta,
    DataTableMeta,
    DataTableSavedView,
} from '../../Types/data-table';
import { tableMenuButtonClass } from '../../Utils/buttonClasses';
import FormCheckbox from '../Form/FormCheckbox.vue';
import {
    allowedTableColumns,
    dataTableColumnWidthClass,
    normalizeTableColumnVisibility,
    orderTableColumns,
    visibilityFromTableColumnKeys,
} from './tableColumnState';
import { readTableLocalState, tableStateStorageKey, writeTableLocalState } from './tableLocalState';
import { dataTableCellContentClass, dataTableFormatUsesOverflowTooltip } from './dataTableCellPresentation';
import {
    initialTablePagination,
    initialTableSorting,
    tablePageCount,
    tablePageSelectOptions,
    tablePageSizes,
} from './tablePaginationState';
import { appliedTableFilters, tableQueryPayload, type TableQueryPrimitive } from './tableQueryState';
import { buildSavedViewState, rememberSavedView } from './tableSavedViewState';
import { selectedTableRowIds, selectEveryTableRow, type TableRowSelection } from './tableSelectionState';
import { useDataTableActions } from './useDataTableActions';
import { useDataTableSavedViews } from './useDataTableSavedViews';

export interface DataTableControllerProps<TRow extends Record<string, unknown>> {
    title: string;
    rows: TRow[];
    columns: DataTableColumn<TRow>[];
    rowKey: keyof TRow & string;
    actions: DataTableAction<TRow>[];
    bulkActions: DataTableBulkAction[];
    emptyLabel?: string;
    totalRows?: number;
    uiLocale?: string;
    stateKey?: string;
    filters?: Record<string, TableQueryPrimitive>;
    exportKey?: string;
    exports?: DataTableExportMeta;
    bulkActionHandler?: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void | Promise<void>;
    table?: DataTableMeta;
    loading: boolean;
    errorLabel: string | null;
    rowClass?: (row: TRow) => string;
}

export function useDataTableController<TRow extends Record<string, unknown>>(
    props: Readonly<DataTableControllerProps<TRow>>,
    emitBulkAction: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void,
) {
    const { t } = useTranslator(props.uiLocale);
    const formatting = createDataTableFormatting(t, props.uiLocale);
    const serverDriven = computed(() => props.table !== undefined);
    const allowedColumns = computed(() => allowedTableColumns(props.columns));
    const localStorageKey = tableStateStorageKey(props.stateKey);
    const localState = readTableLocalState(
        !serverDriven.value && typeof window !== 'undefined' ? window.localStorage : undefined,
        localStorageKey,
    );
    const sorting = ref(initialTableSorting(props.table?.state, localState.sorting));
    const globalFilter = ref(serverDriven.value ? (props.table?.state.search ?? '') : (localState.globalFilter ?? ''));
    const pagination = ref(initialTablePagination(props.table?.state, localState.pagination));
    const rowSelection = ref<TableRowSelection>({});
    const columnVisibility = ref<VisibilityState>(
        serverDriven.value && props.table !== undefined
            ? visibilityFromTableColumnKeys(allowedColumns.value, props.table.state.columns)
            : normalizeTableColumnVisibility(allowedColumns.value, localState.columnVisibility),
    );
    const selectedViewId = ref(props.table?.state.view ?? '');
    const savedViewName = ref('');
    const savedViewType = ref<'private' | 'team'>('private');
    const columnsMenu = ref<HTMLDetailsElement | null>(null);
    let serverSyncTimer: ReturnType<typeof window.setTimeout> | undefined;
    let syncingServerState = false;

    const selectedViewStorageKey = computed(() => (props.table === undefined ? null : `atlas.table.${props.table.key}.selectedView`));
    const selectable = computed(() => props.bulkActions.length > 0);
    const orderedColumns = computed(() => orderTableColumns(allowedColumns.value, props.table?.state.columnOrder ?? []));
    const tableColumns = computed<ColumnDef<TRow, unknown>[]>(() => {
        const dataColumns = orderedColumns.value.map(
            (column) =>
                ({
                    id: column.key,
                    accessorFn: (row: TRow): unknown => row[column.key],
                    header: column.label,
                    enableSorting: column.sortable !== false,
                    cell: (info) => formatting.formatCell(info.getValue(), column.format),
                }) satisfies ColumnDef<TRow, unknown>,
        );

        if (!selectable.value) return dataColumns;
        return [
            {
                id: 'select',
                header: ({ table }) =>
                    h(FormCheckbox, {
                        modelValue: table.getIsAllPageRowsSelected(),
                        indeterminate: table.getIsSomePageRowsSelected(),
                        ariaLabel: t('datatable.select_visible_rows'),
                        'onUpdate:modelValue': (checked: boolean | string[]) => table.toggleAllPageRowsSelected(Boolean(checked)),
                    }),
                cell: ({ row }) =>
                    h(FormCheckbox, {
                        modelValue: row.getIsSelected(),
                        disabled: !row.getCanSelect(),
                        ariaLabel: t('datatable.select_row'),
                        'onUpdate:modelValue': (checked: boolean | string[]) => row.toggleSelected(Boolean(checked)),
                    }),
            },
            ...dataColumns,
        ];
    });

    const table = useVueTable({
        get data() {
            return props.rows;
        },
        get columns() {
            return tableColumns.value;
        },
        state: {
            get sorting() {
                return sorting.value;
            },
            get globalFilter() {
                return globalFilter.value;
            },
            get rowSelection() {
                return rowSelection.value;
            },
            get columnVisibility() {
                return columnVisibility.value;
            },
            get pagination() {
                return pagination.value;
            },
        },
        enableRowSelection: true,
        getRowId: (row) => rowId(row),
        onSortingChange: (updater) => {
            sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
        },
        onGlobalFilterChange: (updater) => {
            globalFilter.value = typeof updater === 'function' ? updater(globalFilter.value) : updater;
        },
        onRowSelectionChange: (updater) => {
            rowSelection.value = typeof updater === 'function' ? updater(rowSelection.value) : updater;
        },
        onColumnVisibilityChange: (updater) => {
            columnVisibility.value = typeof updater === 'function' ? updater(columnVisibility.value) : updater;
        },
        onPaginationChange: (updater) => {
            pagination.value = typeof updater === 'function' ? updater(pagination.value) : updater;
        },
        manualFiltering: serverDriven.value,
        manualSorting: serverDriven.value,
        manualPagination: serverDriven.value,
        get pageCount() {
            return serverDriven.value
                ? tablePageCount(props.table?.pagination.total ?? props.rows.length, pagination.value.pageSize)
                : undefined;
        },
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: serverDriven.value ? undefined : getFilteredRowModel(),
        getSortedRowModel: serverDriven.value ? undefined : getSortedRowModel(),
        getPaginationRowModel: serverDriven.value ? undefined : getPaginationRowModel(),
    });

    const visibleDataColumns = computed(() =>
        orderedColumns.value.filter((column) => table.getColumn(column.key)?.getIsVisible() ?? false),
    );
    const selectedRowIds = computed(() => selectedTableRowIds(rowSelection.value));
    const selectedCount = computed(() => selectedRowIds.value.length);
    const pageSelectOptions = computed(() => tablePageSelectOptions(table.getPageCount() || 1));
    const pageSizeSelectOptions = tablePageSizes.map((value) => ({ value, label: String(value) }));
    const savedViewOptions = computed(() => [
        { value: '', label: t('datatable.views.current') },
        ...(props.table?.savedViews ?? []).map((view) => ({
            value: view.publicId,
            label: `${view.name}${view.isDefault ? ` (${t('datatable.views.default_suffix')})` : ''}`,
        })),
    ]);
    const renderedColumnCount = computed(
        () => visibleDataColumns.value.length + (selectable.value ? 1 : 0) + (props.actions.length > 0 ? 1 : 0),
    );
    const tableRenderKey = computed(() =>
        [
            props.table?.key ?? props.stateKey ?? props.title,
            selectedViewId.value,
            sorting.value.map((sort) => `${sort.id}:${sort.desc ? 'desc' : 'asc'}`).join('|'),
            globalFilter.value,
            pagination.value.pageIndex,
            pagination.value.pageSize,
            visibleDataColumns.value.map((column) => column.key).join('|'),
            orderedColumns.value.map((column) => column.key).join('|'),
        ].join('::'),
    );
    const actionColumnWidth = computed(() => `${Math.max(8, props.actions.length * 2.5 + 2)}rem`);
    const actionColumnStyle = computed(() => ({ minWidth: actionColumnWidth.value, width: actionColumnWidth.value }));
    const exportMeta = computed(() => props.table?.exports ?? props.exports);

    function currentFilterState(): Record<string, TableQueryPrimitive> {
        return appliedTableFilters(props.table?.state.filters, props.filters, typeof window === 'undefined' ? '' : window.location.search);
    }

    function currentServerState(): Record<string, string | number> {
        return tableQueryPayload({
            pagination: pagination.value,
            sorting: sorting.value,
            search: globalFilter.value,
            visibility: columnVisibility.value,
            columns: allowedColumns.value,
            orderedColumnKeys: orderedColumns.value.map((column) => column.key),
            selectedViewId: selectedViewId.value,
            serverState: props.table?.state,
            filters: currentFilterState(),
        });
    }

    function withServerStateSync(operation: () => void): void {
        syncingServerState = true;
        operation();
        void nextTick(() => {
            syncingServerState = false;
        });
    }

    function syncServerStateFromProps(): void {
        if (props.table === undefined) return;
        withServerStateSync(() => {
            sorting.value = initialTableSorting(props.table?.state);
            globalFilter.value = props.table?.state.search ?? '';
            pagination.value = initialTablePagination(props.table?.state);
            columnVisibility.value = visibilityFromTableColumnKeys(allowedColumns.value, props.table?.state.columns ?? []);
            selectedViewId.value = props.table?.state.view ?? '';
            rememberSavedView(
                typeof window === 'undefined' ? undefined : window.sessionStorage,
                selectedViewStorageKey.value,
                selectedViewId.value,
            );
            rowSelection.value = {};
        });
    }

    function scheduleServerSync(resetPage = false): void {
        if (!serverDriven.value || syncingServerState || typeof window === 'undefined') return;
        if (resetPage) pagination.value = { ...pagination.value, pageIndex: 0 };
        if (serverSyncTimer !== undefined) window.clearTimeout(serverSyncTimer);
        serverSyncTimer = window.setTimeout(() => {
            router.get(window.location.pathname, currentServerState(), { preserveScroll: true, preserveState: true, replace: true });
        }, 250);
    }

    function savedViewState() {
        return buildSavedViewState({
            sorting: sorting.value,
            search: globalFilter.value,
            visibility: columnVisibility.value,
            columns: allowedColumns.value,
            orderedColumnKeys: orderedColumns.value.map((column) => column.key),
            serverState: props.table?.state,
            filters: currentFilterState(),
        });
    }

    function applyViewStateLocally(view: DataTableSavedView): void {
        const columns =
            view.state.columns ??
            props.table?.state.columns ??
            allowedColumns.value.filter((column) => column.visibility !== 'hidden' && !column.hidden).map((column) => column.key);
        withServerStateSync(() => {
            sorting.value = [
                { id: view.state.sort ?? props.table?.state.sort ?? props.columns[0]?.key ?? '', desc: view.state.direction === 'desc' },
            ];
            globalFilter.value = view.state.search ?? '';
            pagination.value = { ...pagination.value, pageIndex: 0 };
            columnVisibility.value = visibilityFromTableColumnKeys(allowedColumns.value, columns);
            rowSelection.value = {};
        });
    }

    const { applySavedView, copyView, deleteView, makeDefaultView, saveView, selectedView, updateView } = useDataTableSavedViews({
        table: () => props.table,
        fallbackSort: () => props.columns[0]?.key ?? '',
        pagination,
        selectedViewId,
        selectedViewStorageKey,
        savedViewName,
        savedViewType,
        buildState: savedViewState,
        applyLocally: applyViewStateLocally,
        scheduleServerSync,
        copyName: (name) => t('datatable.views.copy_name', { name }),
    });

    function rowId(row: TRow): string {
        return String(row[props.rowKey]);
    }
    function selectAllFiltered(): void {
        rowSelection.value = selectEveryTableRow(table.getRowModel().rows.map((row) => row.id));
    }
    function clearSelection(): void {
        rowSelection.value = {};
    }
    const { actionClass, actionDisabled, actionIcon, actionTooltip, catalogActionIcon, runBulkAction, runRowAction, visibleActions } =
        useDataTableActions({
            actions: () => props.actions,
            selectedRowIds,
            bulkActionHandler: props.bulkActionHandler,
            emitBulkAction,
        });

    function cellTooltipText(value: unknown, columnId: string): string | null {
        const format = props.columns.find((candidate) => candidate.key === columnId)?.format;
        if (!dataTableFormatUsesOverflowTooltip(format)) return null;
        const text = formatting.formattedText(value, format);
        return text === '-' ? null : text;
    }
    function headerCellClass(headerId: string): string {
        return headerId === 'select' ? 'w-12 px-3 py-3 text-center' : `px-4 py-3 ${dataTableColumnWidthClass(props.columns, headerId)}`;
    }
    function bodyCellClass(columnId: string): string {
        return columnId === 'select'
            ? 'w-12 px-3 py-3 text-center'
            : `px-4 py-3 text-zinc-700 dark:text-zinc-200 ${dataTableColumnWidthClass(props.columns, columnId)}`;
    }
    function headerTooltipText(headerId: string): string | null {
        return props.columns.find((candidate) => candidate.key === headerId)?.label ?? null;
    }
    function bodyCellContentClass(columnId: string): string {
        if (columnId === 'select') return 'flex justify-center';
        const format = props.columns.find((candidate) => candidate.key === columnId)?.format;
        return dataTableCellContentClass(format);
    }

    function persistLocalState(): void {
        if (!serverDriven.value)
            writeTableLocalState(typeof window === 'undefined' ? undefined : window.localStorage, localStorageKey, {
                sorting: sorting.value,
                globalFilter: globalFilter.value,
                columnVisibility: columnVisibility.value,
                pagination: pagination.value,
            });
    }
    function closeMenusOnOutsideClick(event: MouseEvent): void {
        if (event.target instanceof Node && columnsMenu.value && !columnsMenu.value.contains(event.target)) columnsMenu.value.open = false;
    }
    watch([sorting, globalFilter, columnVisibility, pagination], persistLocalState, { deep: true });
    watch(() => props.table?.state, syncServerStateFromProps, { deep: true });
    watch(sorting, () => scheduleServerSync(), { deep: true });
    watch(globalFilter, () => scheduleServerSync(true));
    watch(columnVisibility, () => scheduleServerSync(), { deep: true });
    watch(pagination, () => scheduleServerSync(), { deep: true });
    onMounted(() => document.addEventListener('click', closeMenusOnOutsideClick));
    onBeforeUnmount(() => {
        document.removeEventListener('click', closeMenusOnOutsideClick);
        if (serverSyncTimer !== undefined) window.clearTimeout(serverSyncTimer);
    });

    return {
        t,
        table,
        serverDriven,
        allowedColumns,
        orderedColumns,
        visibleDataColumns,
        selectable,
        sorting,
        globalFilter,
        pagination,
        columnVisibility,
        selectedViewId,
        savedViewName,
        savedViewType,
        columnsMenu,
        selectedCount,
        pageSelectOptions,
        pageSizeSelectOptions,
        savedViewOptions,
        renderedColumnCount,
        tableRenderKey,
        actionColumnStyle,
        exportMeta,
        menuButtonClass: tableMenuButtonClass,
        selectionButtonClass:
            'inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40',
        selectionPrimaryButtonClass:
            'text-teal-700 hover:bg-teal-50 hover:text-teal-800 dark:text-teal-300 dark:hover:bg-teal-950 dark:hover:text-teal-200',
        selectionNeutralButtonClass:
            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
        actionToneClass: actionClass,
        catalogActionIcon,
        currentFilterState,
        selectedView,
        applySavedView,
        saveView,
        updateView,
        deleteView,
        copyView,
        makeDefaultView,
        rowId,
        actionIcon,
        actionClass,
        visibleActions,
        actionDisabled,
        actionTooltip,
        runRowAction,
        selectAllFiltered,
        clearSelection,
        runBulkAction,
        cellTooltipText,
        headerCellClass,
        bodyCellClass,
        headerTooltipText,
        bodyCellContentClass,
    };
}
