<script setup lang="ts" generic="TRow extends Record<string, unknown>">
import { router } from '@inertiajs/vue3';
import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
    type ColumnDef,
    type PaginationState,
    type SortingState,
    type VisibilityState,
} from '@tanstack/vue-table';
import {
    IconChevronDown,
    IconChevronLeft,
    IconChevronRight,
    IconChevronUp,
    IconCircleCheck,
    IconCopy,
    IconDeviceFloppy,
    IconEraser,
    IconExternalLink,
    IconKey,
    IconLockOpen,
    IconLogout,
    IconMailCheck,
    IconPencil,
    IconPlayerPlay,
    IconRefresh,
    IconSearch,
    IconSelectAll,
    IconSelector,
    IconSettings,
    IconStar,
    IconTrash,
    IconUserCheck,
    IconUserOff,
    IconUserScan,
} from '@tabler/icons-vue';
import { computed, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Component, VNodeChild } from 'vue';

import { useModal } from '../Composables/useModal';
import { useToast } from '../Composables/useToast';
import type { TranslationKey } from '../Localization/catalog';
import { useTranslator } from '../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta, DataTableSavedView } from '../Types/data-table';
import {
    formatDate,
    formatEmpty,
    formatFileSize,
    formatMoney,
    formatNumber,
    formatPercent,
    formatStatus,
    formatTimestamp,
    formatTime,
} from '../Utils/formatters';
import FormCheckbox from './Form/FormCheckbox.vue';
import FormInput from './Form/FormInput.vue';
import FormSelect from './Form/FormSelect.vue';
import OverflowTooltip from './OverflowTooltip.vue';
import SeverityBadge from './SeverityBadge.vue';
import StatusBadge from './StatusBadge.vue';
import Tooltip from './Tooltip.vue';

const props = withDefaults(
    defineProps<{
        title: string;
        rows: TRow[];
        columns: DataTableColumn<TRow>[];
        rowKey: keyof TRow & string;
        actions?: DataTableAction<TRow>[];
        bulkActions?: DataTableBulkAction[];
        emptyLabel?: string;
        totalRows?: number;
        uiLocale?: string;
        stateKey?: string;
        bulkActionHandler?: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void | Promise<void>;
        table?: DataTableMeta;
        loading?: boolean;
        errorLabel?: string | null;
    }>(),
    {
        actions: () => [],
        bulkActions: () => [],
        emptyLabel: undefined,
        totalRows: undefined,
        uiLocale: undefined,
        stateKey: undefined,
        bulkActionHandler: undefined,
        table: undefined,
        loading: false,
        errorLabel: null,
    },
);

const emit = defineEmits<{
    bulkAction: [payload: { action: DataTableBulkAction; rowIds: string[] }];
}>();

interface PersistedTableState {
    sorting?: SortingState;
    globalFilter?: string;
    columnVisibility?: VisibilityState;
    pagination?: PaginationState;
}

interface SavedViewStatePayload {
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

type QueryPrimitive = string | number | boolean | null | undefined;

const tableQueryKeys = new Set(['page', 'per_page', 'sort', 'direction', 'search', 'columns', 'column_order', 'view']);
const defaultColumnVisibility = (): VisibilityState =>
    Object.fromEntries(props.columns.map((column) => [column.key, column.hidden !== true]));
const serverDriven = computed(() => props.table !== undefined);
const selectedViewStorageKey = computed(() => (props.table === undefined ? null : `atlas.table.${props.table.key}.selectedView`));
const persistedState = readPersistedState();
const sorting = ref<SortingState>(initialSorting());
const globalFilter = ref(serverDriven.value ? (props.table?.state.search ?? '') : (persistedState.globalFilter ?? ''));
const pagination = ref<PaginationState>(
    serverDriven.value
        ? { pageIndex: Math.max(0, (props.table?.state.page ?? 1) - 1), pageSize: props.table?.state.perPage ?? 10 }
        : (persistedState.pagination ?? { pageIndex: 0, pageSize: 10 }),
);
const rowSelection = ref({});
const columnsMenu = ref<HTMLDetailsElement | null>(null);
const viewsMenu = ref<HTMLDetailsElement | null>(null);
const { t } = useTranslator(props.uiLocale);
const { busy, confirm } = useModal();
const toast = useToast();
const columnVisibility = ref<VisibilityState>(normalizeColumnVisibility(persistedState.columnVisibility));
const savedViewName = ref('');
const savedViewType = ref<'private' | 'team'>('private');
const selectedViewId = ref(props.table?.state.view ?? '');
let serverSyncTimer: ReturnType<typeof window.setTimeout> | undefined;
let syncingServerState = false;

const selectable = computed(() => props.bulkActions.length > 0);
const orderedColumns = computed(() => {
    const order = props.table?.state.columnOrder ?? [];

    if (order.length === 0) {
        return props.columns;
    }

    const rank = new Map(order.map((key, index) => [key, index]));

    return [...props.columns].sort(
        (first, second) => (rank.get(first.key) ?? Number.MAX_SAFE_INTEGER) - (rank.get(second.key) ?? Number.MAX_SAFE_INTEGER),
    );
});

const tableColumns = computed<ColumnDef<TRow, unknown>[]>(() => {
    const dataColumns = orderedColumns.value.map(
        (column) =>
            ({
                id: column.key,
                accessorFn: (row: TRow): unknown => row[column.key],
                header: column.label,
                enableSorting: column.sortable !== false,
                cell: (info) => formatCell(info.getValue(), column.format),
            }) satisfies ColumnDef<TRow, unknown>,
    );

    if (!selectable.value) {
        return dataColumns;
    }

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
        if (!serverDriven.value) {
            return undefined;
        }

        const total = props.table?.pagination.total ?? props.rows.length;
        const pageSize = pagination.value.pageSize || 10;

        return Math.max(1, Math.ceil(total / pageSize));
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: serverDriven.value ? undefined : getFilteredRowModel(),
    getSortedRowModel: serverDriven.value ? undefined : getSortedRowModel(),
    getPaginationRowModel: serverDriven.value ? undefined : getPaginationRowModel(),
});

const visibleDataColumns = computed(() => orderedColumns.value.filter((column) => table.getColumn(column.key)?.getIsVisible() ?? false));
const selectedCount = computed(() => Object.keys(rowSelection.value).length);
const selectedRowIds = computed(() => Object.keys(rowSelection.value));
const pageOptions = computed(() => Array.from({ length: table.getPageCount() || 1 }, (_, index) => index));
const pageSelectOptions = computed(() => pageOptions.value.map((index) => ({ value: index, label: String(index + 1) })));
const pageSizeOptions = [10, 25, 50, 100, 250];
const pageSizeSelectOptions = pageSizeOptions.map((size) => ({ value: size, label: String(size) }));
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
const menuButtonClass =
    'inline-flex h-9 cursor-pointer list-none items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50';
const selectionButtonClass =
    'inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40';
const selectionPrimaryButtonClass =
    'text-teal-700 hover:bg-teal-50 hover:text-teal-800 dark:text-teal-300 dark:hover:bg-teal-950 dark:hover:text-teal-200';
const selectionNeutralButtonClass =
    'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50';
const paginationButtonClass =
    'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-zinc-300 disabled:hover:bg-white disabled:hover:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50 dark:disabled:hover:border-zinc-700 dark:disabled:hover:bg-zinc-900 dark:disabled:hover:text-zinc-200';
const actionColumnWidth = computed(() => `${Math.max(8, props.actions.length * 2.5 + 2)}rem`);
const actionColumnStyle = computed(() => ({ minWidth: actionColumnWidth.value, width: actionColumnWidth.value }));

function stateStorageKey(): string | null {
    return props.stateKey ? `atlas.datatable.${props.stateKey}` : null;
}

function readPersistedState(): PersistedTableState {
    if (serverDriven.value) {
        return {};
    }

    const key = stateStorageKey();

    if (key === null || typeof window === 'undefined') {
        return {};
    }

    try {
        const rawValue = window.localStorage.getItem(key);
        const parsed = rawValue === null ? null : JSON.parse(rawValue);

        if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return parsed as PersistedTableState;
        }
    } catch {
        window.localStorage.removeItem(key);
    }

    return {};
}

function normalizeColumnVisibility(persisted?: VisibilityState): VisibilityState {
    const defaults = defaultColumnVisibility();

    if (serverDriven.value && props.table !== undefined) {
        return visibilityFromColumnKeys(props.table.state.columns);
    }

    if (persisted === undefined) {
        return defaults;
    }

    return Object.fromEntries(props.columns.map((column) => [column.key, persisted[column.key] ?? defaults[column.key] ?? true]));
}

function visibilityFromColumnKeys(columns: string[]): VisibilityState {
    return Object.fromEntries(props.columns.map((column) => [column.key, columns.includes(column.key)]));
}

function persistState(): void {
    if (serverDriven.value) {
        return;
    }

    const key = stateStorageKey();

    if (key === null || typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        key,
        JSON.stringify({
            sorting: sorting.value,
            globalFilter: globalFilter.value,
            columnVisibility: columnVisibility.value,
            pagination: pagination.value,
        } satisfies PersistedTableState),
    );
}

function initialSorting(): SortingState {
    if (props.table !== undefined) {
        return [{ id: props.table.state.sort, desc: props.table.state.direction === 'desc' }];
    }

    return persistedState.sorting ?? [];
}

function serverColumnVisibility(): VisibilityState {
    if (props.table === undefined) {
        return normalizeColumnVisibility();
    }

    return visibilityFromColumnKeys(props.table.state.columns);
}

function withServerStateSync(operation: () => void): void {
    syncingServerState = true;
    operation();
    void nextTick(() => {
        syncingServerState = false;
    });
}

function syncServerStateFromProps(): void {
    if (props.table === undefined) {
        return;
    }

    withServerStateSync(() => {
        sorting.value = [{ id: props.table?.state.sort ?? props.columns[0]?.key ?? '', desc: props.table?.state.direction === 'desc' }];
        globalFilter.value = props.table?.state.search ?? '';
        pagination.value = {
            pageIndex: Math.max(0, (props.table?.state.page ?? 1) - 1),
            pageSize: props.table?.state.perPage ?? 10,
        };
        columnVisibility.value = serverColumnVisibility();
        selectedViewId.value = props.table?.state.view ?? '';
        rememberSelectedView(selectedViewId.value);
        rowSelection.value = {};
    });
}

function applyViewStateLocally(view: DataTableSavedView): void {
    const columns =
        view.state.columns ?? props.table?.state.columns ?? props.columns.filter((column) => !column.hidden).map((column) => column.key);

    withServerStateSync(() => {
        sorting.value = [
            { id: view.state.sort ?? props.table?.state.sort ?? props.columns[0]?.key ?? '', desc: view.state.direction === 'desc' },
        ];
        globalFilter.value = view.state.search ?? '';
        pagination.value = { ...pagination.value, pageIndex: 0 };
        columnVisibility.value = visibilityFromColumnKeys(columns);
        rowSelection.value = {};
    });
}

function currentServerState(): Record<string, string | number> {
    const currentSort = sorting.value[0];
    const visibleColumns = props.columns.filter((column) => columnVisibility.value[column.key] ?? true).map((column) => column.key);

    return {
        page: pagination.value.pageIndex + 1,
        per_page: pagination.value.pageSize,
        sort: currentSort?.id ?? props.table?.state.sort ?? props.columns[0]?.key ?? '',
        direction: currentSort?.desc ? 'desc' : 'asc',
        search: globalFilter.value,
        columns: visibleColumns.join(','),
        column_order: orderedColumns.value.map((column) => column.key).join(','),
        view: selectedViewId.value,
        ...queryFilters(currentFilterState()),
    };
}

function currentFilterState(): Record<string, QueryPrimitive> {
    const filters: Record<string, QueryPrimitive> = { ...(props.table?.state.filters ?? {}) };

    if (typeof window === 'undefined') {
        return filters;
    }

    for (const [key, value] of new URLSearchParams(window.location.search).entries()) {
        if (!tableQueryKeys.has(key)) {
            filters[key] = value;
        }
    }

    return filters;
}

function queryFilters(filters?: Record<string, QueryPrimitive>): Record<string, string | number> {
    if (filters === undefined) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(filters)
            .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
            .map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : (value as string | number)]),
    );
}

function scheduleServerSync(resetPage = false): void {
    if (!serverDriven.value || syncingServerState || typeof window === 'undefined') {
        return;
    }

    if (resetPage) {
        pagination.value = { ...pagination.value, pageIndex: 0 };
    }

    if (serverSyncTimer !== undefined) {
        window.clearTimeout(serverSyncTimer);
    }

    serverSyncTimer = window.setTimeout(() => {
        router.get(window.location.pathname, currentServerState(), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, 250);
}

function savedViewState(): SavedViewStatePayload {
    const currentSort = sorting.value[0];

    return {
        sort: currentSort?.id ?? props.table?.state.sort ?? props.columns[0]?.key ?? '',
        direction: currentSort?.desc ? 'desc' : 'asc',
        search: globalFilter.value,
        columns: props.columns.filter((column) => columnVisibility.value[column.key] ?? true).map((column) => column.key),
        columnOrder: orderedColumns.value.map((column) => column.key),
        filters: queryFilters(currentFilterState()),
        grouping: props.table?.state.grouping ?? [],
        timeRange: props.table?.state.timeRange ?? null,
    };
}

function selectedView(): DataTableSavedView | undefined {
    return props.table?.savedViews.find((view) => view.publicId === selectedViewId.value);
}

function applySavedView(viewId: string | number): void {
    selectedViewId.value = String(viewId);
    rememberSelectedView(selectedViewId.value);
    const view = selectedView();

    if (view === undefined) {
        scheduleServerSync(true);
        return;
    }

    applyViewStateLocally(view);

    router.get(
        window.location.pathname,
        {
            page: 1,
            per_page: pagination.value.pageSize,
            sort: view.state.sort ?? props.table?.state.sort ?? props.columns[0]?.key ?? '',
            direction: view.state.direction ?? 'asc',
            search: view.state.search ?? '',
            columns: (view.state.columns ?? []).join(','),
            column_order: (view.state.columnOrder ?? []).join(','),
            view: view.publicId,
            ...queryFilters(view.state.filters),
        },
        { preserveScroll: true, preserveState: false, replace: true },
    );
}

function rememberSelectedView(viewId: string): void {
    if (typeof window === 'undefined' || selectedViewStorageKey.value === null) {
        return;
    }

    if (viewId === '') {
        window.sessionStorage.removeItem(selectedViewStorageKey.value);
        return;
    }

    window.sessionStorage.setItem(selectedViewStorageKey.value, viewId);
}

function saveView(): void {
    if (props.table === undefined || savedViewName.value.trim() === '') {
        return;
    }

    router.post(
        '/admin/table-views',
        {
            table_key: props.table.key,
            name: savedViewName.value.trim(),
            type: savedViewType.value,
            state: savedViewState(),
        },
        { preserveScroll: true, preserveState: false },
    );
}

function updateView(): void {
    const view = selectedView();

    if (view === undefined || view.type === 'system') {
        return;
    }

    router.patch(
        `/admin/table-views/${view.publicId}`,
        {
            name: savedViewName.value.trim() || view.name,
            state: savedViewState(),
        },
        { preserveScroll: true, preserveState: false },
    );
}

function deleteView(): void {
    const view = selectedView();

    if (view === undefined || view.type === 'system') {
        return;
    }

    router.delete(`/admin/table-views/${view.publicId}`, { preserveScroll: true, preserveState: false });
}

function copyView(): void {
    const view = selectedView();

    if (view === undefined) {
        return;
    }

    router.post(
        `/admin/table-views/${view.publicId}/copy`,
        {
            name: savedViewName.value.trim() || t('datatable.views.copy_name', { name: view.name }),
            type: savedViewType.value,
        },
        { preserveScroll: true, preserveState: false },
    );
}

function makeDefaultView(): void {
    const view = selectedView();

    if (view === undefined) {
        return;
    }

    router.post(`/admin/table-views/${view.publicId}/default`, {}, { preserveScroll: true, preserveState: false });
}

function formatCell(value: unknown, format: DataTableColumn<TRow>['format']): VNodeChild {
    if (format === 'boolean') {
        return h(StatusBadge, {
            value: value === true,
            trueLabel: t('datatable.boolean.yes'),
            falseLabel: t('datatable.boolean.no'),
        });
    }

    if (format === 'list' && Array.isArray(value)) {
        return value.join(', ');
    }

    if (format === 'count' && Array.isArray(value)) {
        return String(value.length);
    }

    if (format === 'date' && (typeof value === 'string' || value instanceof Date)) {
        return formatDate(value, props.uiLocale ?? 'en');
    }

    if (format === 'time' && (typeof value === 'string' || value instanceof Date)) {
        return formatTime(value, props.uiLocale ?? 'en');
    }

    if (format === 'datetime' && (typeof value === 'string' || value instanceof Date)) {
        return formatTimestamp(value, props.uiLocale ?? 'pl');
    }

    if (format === 'money' && value !== null && typeof value === 'object' && 'amountMinor' in value && 'currency' in value) {
        return formatMoney(value as { amountMinor: number; currency: string }, props.uiLocale ?? 'en');
    }

    if (format === 'file-size' && typeof value === 'number') {
        return formatFileSize(value, props.uiLocale ?? 'en');
    }

    if (format === 'number' && typeof value === 'number') {
        return formatNumber(value, props.uiLocale ?? 'en');
    }

    if (format === 'percent' && typeof value === 'number') {
        return formatPercent(value, props.uiLocale ?? 'en');
    }

    if (format === 'status' && typeof value === 'string') {
        return localizedStatus(value);
    }

    if (format === 'severity' && typeof value === 'string') {
        return h(SeverityBadge, { value, label: localizedStatus(value) });
    }

    return formatEmpty(value);
}

function cellTooltipText(value: unknown, columnId: string): string | null {
    const column = props.columns.find((candidate) => candidate.key === columnId);
    const format = column?.format;

    if (format === 'boolean' || format === 'severity' || format === 'status') {
        return null;
    }

    const text = formattedCellText(value, format);

    if (text === '-') {
        return null;
    }

    return text;
}

function formattedCellText(value: unknown, format: DataTableColumn<TRow>['format']): string {
    if (format === 'list' && Array.isArray(value)) {
        return value.join(', ');
    }

    if (format === 'count' && Array.isArray(value)) {
        return String(value.length);
    }

    if (format === 'date' && (typeof value === 'string' || value instanceof Date)) {
        return formatDate(value, props.uiLocale ?? 'en');
    }

    if (format === 'time' && (typeof value === 'string' || value instanceof Date)) {
        return formatTime(value, props.uiLocale ?? 'en');
    }

    if (format === 'datetime' && (typeof value === 'string' || value instanceof Date)) {
        return formatTimestamp(value, props.uiLocale ?? 'pl');
    }

    if (format === 'money' && value !== null && typeof value === 'object' && 'amountMinor' in value && 'currency' in value) {
        return formatMoney(value as { amountMinor: number; currency: string }, props.uiLocale ?? 'en');
    }

    if (format === 'file-size' && typeof value === 'number') {
        return formatFileSize(value, props.uiLocale ?? 'en');
    }

    if (format === 'number' && typeof value === 'number') {
        return formatNumber(value, props.uiLocale ?? 'en');
    }

    if (format === 'percent' && typeof value === 'number') {
        return formatPercent(value, props.uiLocale ?? 'en');
    }

    if ((format === 'severity' || format === 'status') && typeof value === 'string') {
        return localizedStatus(value);
    }

    return formatEmpty(value);
}

function localizedStatus(value: string): string {
    const normalized = value.toLowerCase().trim().replaceAll(/\s+/gu, '_').replaceAll('-', '_');
    const statusKeys: Record<string, TranslationKey> = {
        active: 'datatable.status.active',
        blocked: 'datatable.status.blocked',
        danger: 'datatable.status.danger',
        disabled: 'datatable.status.disabled',
        enabled: 'datatable.status.enabled',
        error: 'datatable.status.error',
        failed: 'datatable.status.failed',
        failure: 'datatable.status.failed',
        info: 'datatable.status.info',
        inactive: 'datatable.status.inactive',
        ok: 'datatable.status.ok',
        pending: 'datatable.status.pending',
        resolved: 'datatable.status.resolved',
        running: 'datatable.status.running',
        success: 'datatable.status.success',
        warn: 'datatable.status.warning',
        warning: 'datatable.status.warning',
    };
    const key = statusKeys[normalized];

    return key === undefined ? formatStatus(value) : t(key);
}

async function withBusyModal(
    titleKey: TranslationKey,
    descriptionKey: TranslationKey,
    operation: () => void | Promise<void>,
): Promise<void> {
    const close = busy({ titleKey, descriptionKey });

    try {
        await nextTick();
        await new Promise<void>((resolve) => {
            requestAnimationFrame(() => resolve());
        });
        await operation();
    } finally {
        close();
    }
}

function rowId(row: TRow): string {
    const value = row[props.rowKey];

    return String(value);
}

function actionIcon(action: DataTableAction<TRow>): Component {
    const icons: Record<string, Component> = {
        activate: IconUserCheck,
        deactivate: IconUserOff,
        delete: IconTrash,
        edit: IconPencil,
        open: IconExternalLink,
        run: IconPlayerPlay,
        read: IconCircleCheck,
        'mark-read': IconCircleCheck,
        verify: IconMailCheck,
        'first-password': IconKey,
        unlock: IconLockOpen,
        'reset-mfa': IconRefresh,
        'invalidate-sessions': IconLogout,
        impersonate: IconUserScan,
    };

    return icons[action.key] ?? IconSettings;
}

function actionTone(action: DataTableAction<TRow>): DataTableAction<TRow>['tone'] {
    if (action.tone) {
        return action.tone;
    }

    if (action.method === 'delete' || action.key.includes('delete') || action.key.includes('deactivate')) {
        return 'danger';
    }

    if (action.key.includes('require') && action.key.includes('verification')) {
        return 'warning';
    }

    if (action.key === 'open') {
        return 'info';
    }

    if (action.key.includes('read') || action.key.includes('activate') || action.key.includes('verify') || action.key.includes('unlock')) {
        return 'success';
    }

    if (action.key.includes('reset') || action.key.includes('password')) {
        return 'warning';
    }

    return 'neutral';
}

function actionClass(action: DataTableAction<TRow>): string {
    const classes = {
        neutral:
            'border-zinc-300 text-zinc-600 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
        info: 'border-sky-200 text-sky-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 dark:border-sky-900 dark:text-sky-300 dark:hover:bg-sky-950',
        success:
            'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950',
        warning:
            'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950',
        danger: 'border-rose-200 text-rose-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950',
    };

    return classes[actionTone(action) ?? 'neutral'];
}

function visibleActions(row: TRow): DataTableAction<TRow>[] {
    return props.actions.filter((action) => action.visible?.(row) ?? true);
}

async function runRowAction(action: DataTableAction<TRow>, row: TRow): Promise<void> {
    const confirmationSubject = typeof action.confirm === 'function' ? action.confirm(row) : action.confirm;

    if (
        confirmationSubject !== undefined &&
        !(await confirm({
            titleKey: 'datatable.action.confirm.title',
            descriptionKey: 'datatable.action.confirm.description',
            confirmKey: 'datatable.action.confirm.confirm',
            cancelKey: 'datatable.action.confirm.cancel',
            tone: actionTone(action) === 'danger' ? 'danger' : 'warning',
            subject: confirmationSubject,
        }))
    ) {
        return;
    }

    router.visit(action.href(row), {
        method: action.method ?? 'get',
        preserveScroll: true,
    });
}

function bulkActionIcon(action: DataTableBulkAction): Component {
    if (action.key.includes('deactivate')) {
        return IconUserOff;
    }

    if (action.key.includes('activate')) {
        return IconUserCheck;
    }

    if (action.key.includes('verify') || action.key.includes('verification')) {
        return IconMailCheck;
    }

    if (action.key.includes('read')) {
        return IconCircleCheck;
    }

    if (action.key.includes('password') || action.key.includes('link')) {
        return IconKey;
    }

    if (action.key.includes('unlock')) {
        return IconLockOpen;
    }

    if (action.key.includes('reset')) {
        return IconRefresh;
    }

    if (action.key.includes('delete') || action.key.includes('destroy')) {
        return IconTrash;
    }

    return IconSettings;
}

function bulkActionClass(action: DataTableBulkAction): string {
    const tone = action.tone ?? 'neutral';
    const classes = {
        neutral:
            'border-zinc-300 text-zinc-600 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
        info: 'border-sky-200 text-sky-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 dark:border-sky-900 dark:text-sky-300 dark:hover:bg-sky-950',
        success:
            'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950',
        warning:
            'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950',
        danger: 'border-rose-200 text-rose-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950',
    };

    return classes[tone];
}

function headerCellClass(headerId: string): string {
    if (headerId === 'select') {
        return 'w-12 px-3 py-3 text-center';
    }

    return `px-4 py-3 ${dataColumnWidthClass(headerId)}`;
}

function bodyCellClass(columnId: string): string {
    if (columnId === 'select') {
        return 'w-12 px-3 py-3 text-center';
    }

    return `px-4 py-3 text-zinc-700 dark:text-zinc-200 ${dataColumnWidthClass(columnId)}`;
}

function headerTooltipText(headerId: string): string | null {
    const column = props.columns.find((candidate) => candidate.key === headerId);

    return column?.label ?? null;
}

function bodyCellContentClass(columnId: string): string {
    if (columnId === 'select') {
        return 'flex justify-center';
    }

    const column = props.columns.find((candidate) => candidate.key === columnId);

    if (column?.format === 'boolean' || column?.format === 'severity') {
        return 'inline-flex max-w-full overflow-visible py-0.5 align-middle';
    }

    return 'block min-w-0 truncate';
}

function dataColumnWidthClass(columnId: string): string {
    const column = props.columns.find((candidate) => candidate.key === columnId);

    if (column === undefined) {
        return 'min-w-44';
    }

    if (column.key.toLowerCase().includes('email')) {
        return 'min-w-56';
    }

    if (column.key.toLowerCase().includes('publicid') || column.key.toLowerCase().includes('public_id')) {
        return 'min-w-36';
    }

    if (column.format === 'boolean' || column.format === 'count' || column.format === 'number' || column.format === 'percent') {
        return 'min-w-28';
    }

    if (column.format === 'date' || column.format === 'time' || column.format === 'datetime') {
        return 'min-w-40';
    }

    if (column.format === 'money' || column.format === 'file-size') {
        return 'min-w-36';
    }

    if (column.format === 'list') {
        return 'min-w-56';
    }

    return 'min-w-44';
}

function selectAllFiltered(): void {
    rowSelection.value = Object.fromEntries(table.getRowModel().rows.map((row) => [row.id, true]));
}

function clearSelection(): void {
    rowSelection.value = {};
}

function runBulkAction(action: DataTableBulkAction): void {
    if (selectedRowIds.value.length === 0) {
        toast.warning('datatable.bulk.no_selection');
        return;
    }

    void confirmBulkAction(action);
}

async function confirmBulkAction(action: DataTableBulkAction): Promise<void> {
    if (
        selectedRowIds.value.length > 5000 &&
        !(await confirm({
            titleKey: 'datatable.bulk.large.title',
            descriptionKey: 'datatable.bulk.large.description',
            confirmKey: 'datatable.bulk.large.confirm',
            cancelKey: 'datatable.bulk.large.cancel',
            tone: 'warning',
        }))
    ) {
        return;
    }

    const payload = {
        action,
        rowIds: selectedRowIds.value,
    };

    if (action.execution === 'queued') {
        await props.bulkActionHandler?.(payload);
        emit('bulkAction', payload);
        return;
    }

    await withBusyModal('datatable.bulk.processing.title', 'datatable.bulk.processing.description', async () => {
        await props.bulkActionHandler?.(payload);
        emit('bulkAction', payload);
    });
}

function closeMenusOnOutsideClick(event: MouseEvent): void {
    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (columnsMenu.value && !columnsMenu.value.contains(target)) {
        columnsMenu.value.open = false;
    }

    if (viewsMenu.value && !viewsMenu.value.contains(target)) {
        viewsMenu.value.open = false;
    }
}

watch([sorting, globalFilter, columnVisibility, pagination], persistState, { deep: true });
watch(() => props.table?.state, syncServerStateFromProps, { deep: true });
watch(sorting, () => scheduleServerSync(), { deep: true });
watch(globalFilter, () => scheduleServerSync(true));
watch(columnVisibility, () => scheduleServerSync(), { deep: true });
watch(pagination, () => scheduleServerSync(), { deep: true });

onMounted(() => {
    document.addEventListener('click', closeMenusOnOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeMenusOnOutsideClick);

    if (serverSyncTimer !== undefined) {
        window.clearTimeout(serverSyncTimer);
    }
});
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <div class="flex flex-wrap items-center gap-2">
                <FormInput
                    v-model="globalFilter"
                    class="w-64 max-w-full"
                    :aria-label="t('datatable.search')"
                    :placeholder="t('datatable.search')"
                    :leading-icon="IconSearch"
                />
                <details v-if="serverDriven" ref="viewsMenu" class="relative">
                    <summary :class="menuButtonClass">
                        <IconSettings aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('datatable.views') }}
                    </summary>
                    <div
                        class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-800 dark:bg-zinc-950"
                    >
                        <FormSelect
                            :model-value="selectedViewId"
                            aria-label="Saved table view"
                            :options="savedViewOptions"
                            button-class="h-9 w-full"
                            @update:model-value="applySavedView"
                        />
                        <FormInput
                            v-model="savedViewName"
                            :aria-label="t('datatable.views.name')"
                            :placeholder="t('datatable.views.name_placeholder')"
                        />
                        <FormSelect
                            v-model="savedViewType"
                            :aria-label="t('datatable.views.type')"
                            :options="[
                                { value: 'private', label: t('datatable.views.private') },
                                { value: 'team', label: t('datatable.views.team') },
                            ]"
                            button-class="h-9 w-full"
                        />
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-teal-700 px-3 text-sm font-medium text-white transition hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500"
                                :disabled="savedViewName.trim() === ''"
                                @click="saveView"
                            >
                                <IconDeviceFloppy aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ t('datatable.views.save') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                :disabled="selectedView() === undefined || selectedView()?.type === 'system'"
                                @click="updateView"
                            >
                                <IconPencil aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ t('datatable.views.update') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                :disabled="selectedView() === undefined"
                                @click="copyView"
                            >
                                <IconCopy aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ t('datatable.views.copy') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                :disabled="selectedView() === undefined"
                                @click="makeDefaultView"
                            >
                                <IconStar aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ t('datatable.views.default') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                                :disabled="selectedView() === undefined || selectedView()?.type === 'system'"
                                @click="deleteView"
                            >
                                <IconTrash aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ t('datatable.views.delete') }}
                            </button>
                        </div>
                    </div>
                </details>
                <details ref="columnsMenu" class="relative">
                    <summary :class="menuButtonClass">
                        <IconSettings aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('datatable.columns') }}
                    </summary>
                    <div
                        class="absolute right-0 z-20 mt-2 w-64 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-800 dark:bg-zinc-950"
                    >
                        <div
                            v-for="column in orderedColumns"
                            :key="column.key"
                            class="flex items-center gap-2 py-1 text-sm text-zinc-700 dark:text-zinc-200"
                        >
                            <FormCheckbox
                                :model-value="table.getColumn(column.key)?.getIsVisible() ?? false"
                                :aria-label="column.label"
                                @update:model-value="table.getColumn(column.key)?.toggleVisibility(Boolean($event))"
                            />
                            {{ column.label }}
                        </div>
                    </div>
                </details>
            </div>
        </div>

        <div
            v-if="selectable"
            class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800 dark:bg-zinc-950"
        >
            <div class="flex flex-wrap items-center gap-2 text-zinc-600 dark:text-zinc-300">
                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{
                    t('datatable.selection.count', { count: selectedCount })
                }}</span>
                <button type="button" :class="[selectionButtonClass, selectionPrimaryButtonClass]" @click="selectAllFiltered">
                    <IconSelectAll aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.select_all_filtered') }}
                </button>
                <button
                    type="button"
                    :class="[selectionButtonClass, selectionNeutralButtonClass]"
                    :disabled="selectedCount === 0"
                    @click="clearSelection"
                >
                    <IconEraser aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.selection.clear') }}
                </button>
            </div>
            <div v-if="bulkActions.length" class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">{{ t('datatable.bulk.actions') }}</span>
                <button
                    v-for="action in bulkActions"
                    :key="action.key"
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border bg-white px-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40 dark:bg-zinc-950"
                    :class="bulkActionClass(action)"
                    :disabled="selectedCount === 0"
                    @click="runBulkAction(action)"
                >
                    <component :is="bulkActionIcon(action)" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ action.label }}
                </button>
            </div>
        </div>

        <div class="relative overflow-visible rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            <div class="overflow-x-auto overflow-y-visible rounded-t-lg">
                <table :key="tableRenderKey" class="w-max min-w-full table-auto divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <colgroup>
                        <col v-if="selectable" class="w-12" />
                        <col v-for="column in visibleDataColumns" :key="column.key" />
                        <col v-if="actions?.length" :style="actionColumnStyle" />
                    </colgroup>
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                            <th v-for="header in headerGroup.headers" :key="header.id" :class="headerCellClass(header.id)">
                                <button
                                    v-if="!header.isPlaceholder"
                                    type="button"
                                    class="inline-flex max-w-full items-center gap-1 text-left"
                                    :class="{ 'cursor-pointer': header.column.getCanSort() }"
                                    @click="header.column.getToggleSortingHandler()?.($event)"
                                >
                                    <Tooltip
                                        v-if="headerTooltipText(header.id) !== null"
                                        :text="headerTooltipText(header.id) ?? ''"
                                        align="start"
                                        placement="top"
                                    >
                                        <span class="block truncate">
                                            <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                        </span>
                                    </Tooltip>
                                    <span v-else class="block truncate">
                                        <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                    </span>
                                    <IconChevronUp v-if="header.column.getIsSorted() === 'asc'" aria-hidden="true" class="h-4 w-4" />
                                    <IconChevronDown
                                        v-else-if="header.column.getIsSorted() === 'desc'"
                                        aria-hidden="true"
                                        class="h-4 w-4"
                                    />
                                    <IconSelector v-else-if="header.column.getCanSort()" aria-hidden="true" class="h-4 w-4 opacity-50" />
                                </button>
                            </th>
                            <th v-if="actions?.length" class="px-4 py-3 text-right" :style="actionColumnStyle">
                                {{ t('datatable.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <tr v-if="loading">
                            <td :colspan="renderedColumnCount" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ t('datatable.loading') }}
                            </td>
                        </tr>
                        <tr v-else-if="errorLabel">
                            <td :colspan="renderedColumnCount" class="px-4 py-10 text-center text-sm text-rose-600 dark:text-rose-300">
                                {{ errorLabel }}
                            </td>
                        </tr>
                        <template v-else>
                            <tr v-for="row in table.getRowModel().rows" :key="rowId(row.original)">
                                <td v-for="cell in row.getVisibleCells()" :key="cell.id" :class="bodyCellClass(cell.column.id)">
                                    <OverflowTooltip
                                        v-if="cellTooltipText(cell.getValue(), cell.column.id) !== null"
                                        :text="cellTooltipText(cell.getValue(), cell.column.id) ?? ''"
                                        full-width
                                        align="start"
                                        placement="top"
                                        :content-class="bodyCellContentClass(cell.column.id)"
                                    >
                                        <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </OverflowTooltip>
                                    <span v-else :class="bodyCellContentClass(cell.column.id)">
                                        <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </span>
                                </td>
                                <td v-if="actions?.length" class="px-4 py-3 text-right" :style="actionColumnStyle">
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <Tooltip
                                            v-for="action in visibleActions(row.original)"
                                            :key="action.key"
                                            :text="action.label"
                                            align="end"
                                            placement="top"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border transition"
                                                :class="actionClass(action)"
                                                :aria-label="action.label"
                                                @click="runRowAction(action, row.original)"
                                            >
                                                <component
                                                    :is="actionIcon(action)"
                                                    aria-hidden="true"
                                                    class="h-4 w-4"
                                                    :stroke-width="1.8"
                                                />
                                            </button>
                                        </Tooltip>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!loading && !errorLabel && table.getRowModel().rows.length === 0">
                            <td :colspan="renderedColumnCount" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ globalFilter ? t('datatable.no_results') : (emptyLabel ?? t('datatable.empty')) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="relative z-10 flex flex-col gap-3 border-t border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="paginationButtonClass"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()"
                    >
                        <IconChevronLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('datatable.previous') }}
                    </button>
                    <button type="button" :class="paginationButtonClass" :disabled="!table.getCanNextPage()" @click="table.nextPage()">
                        {{ t('datatable.next') }}
                        <IconChevronRight aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                    <div class="flex items-center gap-2">
                        <span>{{ t('datatable.rows_per_page') }}</span>
                        <FormSelect
                            :model-value="table.getState().pagination.pageSize"
                            :aria-label="t('datatable.rows_per_page')"
                            :options="pageSizeSelectOptions"
                            button-class="h-9 w-20"
                            @update:model-value="table.setPageSize(Number($event))"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <span>{{ t('datatable.page') }}</span>
                        <FormSelect
                            :model-value="table.getState().pagination.pageIndex"
                            :aria-label="t('datatable.page')"
                            :options="pageSelectOptions"
                            button-class="h-9 w-20"
                            @update:model-value="table.setPageIndex(Number($event))"
                        />
                    </div>
                    <span>
                        {{ t('datatable.page_of', { page: table.getState().pagination.pageIndex + 1, pages: table.getPageCount() || 1 }) }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
