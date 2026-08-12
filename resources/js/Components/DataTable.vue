<script setup lang="ts" generic="TRow extends Record<string, unknown>">
import { FlexRender } from '@tanstack/vue-table';
import { IconChevronDown, IconChevronUp, IconEraser, IconSearch, IconSelectAll, IconSelector, IconSettings } from '@tabler/icons-vue';

import DataTableExportMenu from './DataTableExportMenu.vue';
import DataTablePagination from './DataTable/DataTablePagination.vue';
import DataTableSavedViewsMenu from './DataTable/DataTableSavedViewsMenu.vue';
import DataTableStateRow from './DataTable/DataTableStateRow.vue';
import { useDataTableController } from './DataTable/useDataTableController';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableExportMeta, DataTableMeta } from '../Types/data-table';
import FormCheckbox from './Form/FormCheckbox.vue';
import FormInput from './Form/FormInput.vue';
import OverflowTooltip from './OverflowTooltip.vue';
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
        filters?: Record<string, string | number | boolean | null | undefined>;
        exportKey?: string;
        exports?: DataTableExportMeta;
        bulkActionHandler?: (payload: { action: DataTableBulkAction; rowIds: string[] }) => void | Promise<void>;
        table?: DataTableMeta;
        loading?: boolean;
        errorLabel?: string | null;
        rowClass?: (row: TRow) => string;
    }>(),
    {
        actions: () => [],
        bulkActions: () => [],
        emptyLabel: undefined,
        totalRows: undefined,
        uiLocale: undefined,
        stateKey: undefined,
        filters: undefined,
        exportKey: undefined,
        exports: undefined,
        bulkActionHandler: undefined,
        table: undefined,
        loading: false,
        errorLabel: null,
        rowClass: undefined,
    },
);

const emit = defineEmits<{
    bulkAction: [payload: { action: DataTableBulkAction; rowIds: string[] }];
}>();

const {
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
    menuButtonClass,
    selectionButtonClass,
    selectionPrimaryButtonClass,
    selectionNeutralButtonClass,
    actionToneClass,
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
} = useDataTableController(props, (payload) => emit('bulkAction', payload));
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
                <DataTableSavedViewsMenu
                    v-if="serverDriven && props.table?.capabilities?.savedViews === true"
                    :selected-view-id="selectedViewId"
                    :saved-view-name="savedViewName"
                    :saved-view-type="savedViewType"
                    :saved-view-options="savedViewOptions"
                    :selected-view="selectedView()"
                    :ui-locale="uiLocale"
                    @update:selected-view-id="applySavedView"
                    @update:saved-view-name="savedViewName = $event"
                    @update:saved-view-type="savedViewType = String($event) === 'team' ? 'team' : 'private'"
                    @save="saveView"
                    @update="updateView"
                    @copy="copyView"
                    @make-default="makeDefaultView"
                    @delete="deleteView"
                />
                <DataTableExportMenu
                    v-if="exportMeta && (props.table?.key || exportKey)"
                    :table-key="props.table?.key ?? exportKey ?? ''"
                    :exports="exportMeta"
                    :columns="allowedColumns.filter((column) => columnVisibility[column.key] ?? true).map((column) => column.key)"
                    :column-order="orderedColumns.map((column) => column.key)"
                    :filters="currentFilterState()"
                    :search="globalFilter"
                    :sort="sorting[0]?.id ?? props.table?.state.sort ?? props.columns[0]?.key ?? ''"
                    :direction="sorting[0]?.desc ? 'desc' : 'asc'"
                    :per-page="pagination.pageSize"
                    :ui-locale="uiLocale"
                />
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
                    :class="actionToneClass(action)"
                    :disabled="selectedCount === 0"
                    @click="runBulkAction(action)"
                >
                    <component :is="catalogActionIcon(action)" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
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
                        <DataTableStateRow
                            v-if="loading"
                            :colspan="renderedColumnCount"
                            variant="loading-refresh"
                            :title="t('datatable.loading')"
                        />
                        <DataTableStateRow
                            v-else-if="errorLabel"
                            :colspan="renderedColumnCount"
                            variant="error-recoverable"
                            :title="errorLabel"
                        />
                        <template v-else>
                            <tr v-for="row in table.getRowModel().rows" :key="rowId(row.original)" :class="rowClass?.(row.original)">
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
                                            :text="actionTooltip(action, row.original)"
                                            align="end"
                                            placement="top"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border transition"
                                                :class="[
                                                    actionClass(action),
                                                    actionDisabled(action, row.original) ? 'cursor-not-allowed opacity-40' : '',
                                                ]"
                                                :aria-label="action.label"
                                                :aria-disabled="actionDisabled(action, row.original)"
                                                :disabled="actionDisabled(action, row.original)"
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
                        <DataTableStateRow
                            v-if="!loading && !errorLabel && table.getRowModel().rows.length === 0"
                            :colspan="renderedColumnCount"
                            :variant="globalFilter ? 'no-results' : 'empty'"
                            :title="globalFilter ? t('datatable.no_results') : (emptyLabel ?? t('datatable.empty'))"
                        />
                    </tbody>
                </table>
            </div>
            <DataTablePagination
                :can-previous="table.getCanPreviousPage()"
                :can-next="table.getCanNextPage()"
                :page-index="table.getState().pagination.pageIndex"
                :page-size="table.getState().pagination.pageSize"
                :page-options="pageSelectOptions"
                :page-size-options="pageSizeSelectOptions"
                :page-count="table.getPageCount() || 1"
                @previous="table.previousPage()"
                @next="table.nextPage()"
                @update-page="table.setPageIndex"
                @update-page-size="table.setPageSize"
            />
        </div>
    </section>
</template>
