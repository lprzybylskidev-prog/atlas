<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCircleCheck, IconDatabaseImport, IconProgress } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import ManagedProcessArea from '../../../Components/ManagedProcesses/ManagedProcessArea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { allOptions, processSourceLabel, processStatusLabel } from '../../../Composables/useManagedProcessUi';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ManagedProcessFilterOptions, ManagedProcessRunRow, ManagedProcessSummary } from '../../../Types/managed-processes';
import { moduleLabel } from '../../../Utils/moduleLabels';

const props = defineProps<{
    runs: ManagedProcessRunRow[];
    summary: ManagedProcessSummary;
    filterOptions: ManagedProcessFilterOptions;
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['process', 'status', 'source', 'module', 'import', 'idempotency', 'handling', 'from', 'to'];
const filterDefaults = {
    process: 'all',
    status: 'all',
    source: 'all',
    module: 'all',
    import: 'all',
    idempotency: 'all',
    handling: 'all',
    from: '',
    to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<ManagedProcessRunRow[]>(() =>
    props.runs.map((run) => ({
        ...run,
        moduleKey: moduleLabel(run.moduleKey, t),
        status: processStatusLabel(run.status, t),
        sourceType: processSourceLabel(run.sourceType, t),
        importSourceType: run.importSourceType === null ? '' : processSourceLabel(run.importSourceType, t),
        progressLabel: progressLabel(run),
    })),
);
const columns = computed<DataTableColumn<ManagedProcessRunRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.managed_processes.table.public_id'), hidden: true },
    { key: 'processKey', label: t('pages.admin.managed_processes.process') },
    { key: 'status', label: t('pages.admin.managed_processes.status') },
    { key: 'sourceType', label: t('pages.admin.managed_processes.source') },
    { key: 'moduleKey', label: t('pages.admin.managed_processes.module') },
    { key: 'importKey', label: t('pages.admin.managed_processes.import') },
    { key: 'importSourceType', label: t('pages.admin.managed_processes.import_source'), hidden: true },
    { key: 'importFile', label: t('pages.admin.managed_processes.import_file'), hidden: true },
    { key: 'idempotencyKey', label: t('pages.admin.managed_processes.idempotency_key') },
    { key: 'idempotencyState', label: t('pages.admin.managed_processes.idempotency_state') },
    { key: 'handlingStatus', label: t('pages.admin.managed_processes.handling_status'), format: 'status' },
    { key: 'acknowledgedAt', label: t('pages.admin.managed_processes.handled_at'), format: 'datetime', hidden: true },
    { key: 'acknowledgedBy', label: t('pages.admin.managed_processes.handled_by'), hidden: true },
    { key: 'progressLabel', label: t('pages.admin.managed_processes.progress') },
    { key: 'progressCurrent', label: t('pages.admin.managed_processes.done'), format: 'number', hidden: true },
    { key: 'progressTotal', label: t('pages.admin.managed_processes.total'), format: 'number', hidden: true },
    { key: 'actor', label: t('pages.admin.managed_processes.actor') },
    { key: 'team', label: t('pages.admin.managed_processes.team') },
    { key: 'startedAt', label: t('pages.admin.managed_processes.started'), format: 'datetime' },
    { key: 'finishedAt', label: t('pages.admin.managed_processes.finished'), format: 'datetime' },
    { key: 'createdAt', label: t('pages.admin.managed_processes.created'), format: 'datetime', hidden: true },
    { key: 'queueName', label: t('pages.admin.managed_processes.queue'), hidden: true },
    { key: 'correlationId', label: t('pages.admin.managed_processes.table.correlation_id'), hidden: true },
    { key: 'safeErrorSummary', label: t('pages.admin.managed_processes.table.safe_error_summary'), hidden: true },
]);
const actions = computed<DataTableAction<ManagedProcessRunRow>[]>(() => [
    {
        key: 'show',
        label: t('pages.admin.managed_processes.open_details'),
        href: (run) => `/admin/managed-processes/${encodeURIComponent(run.publicId)}`,
    },
    {
        key: 'acknowledge',
        label: t('pages.admin.managed_processes.acknowledge'),
        method: 'post',
        href: (run) => `/admin/managed-processes/acknowledge?runs[]=${encodeURIComponent(run.publicId)}`,
        confirm: (run) => run.processKey,
        tone: 'success',
        visible: (run) => run.canAcknowledge,
    },
]);
const bulkActions = computed<DataTableBulkAction[]>(() => [
    { key: 'acknowledge', label: t('pages.admin.managed_processes.acknowledge_selected'), tone: 'success' },
]);
const processOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.processes ?? [], t('pages.admin.managed_processes.filters.any_process')),
);
const statusOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.statuses ?? [], t('pages.admin.managed_processes.filters.any_status'), (status) =>
        processStatusLabel(status, t),
    ),
);
const sourceOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.sources ?? [], t('pages.admin.managed_processes.filters.any_source'), (source) =>
        processSourceLabel(source, t),
    ),
);
const moduleOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.modules ?? [], t('pages.admin.managed_processes.filters.any_module'), (module) =>
        moduleLabel(module, t),
    ),
);
const importOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.imports ?? [], t('pages.admin.managed_processes.filters.any_import')),
);
const idempotencyOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.idempotencyStates ?? [], t('pages.admin.managed_processes.filters.any_idempotency')),
);
const handlingOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.managed_processes.filters.any_handling') },
    { value: 'needs_attention', label: t('pages.admin.managed_processes.filters.needs_attention') },
    { value: 'handled', label: t('pages.admin.managed_processes.filters.handled') },
]);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        process: String(props.table.state.filters?.process ?? 'all'),
        status: String(props.table.state.filters?.status ?? 'all'),
        source: String(props.table.state.filters?.source ?? 'all'),
        module: String(props.table.state.filters?.module ?? 'all'),
        import: String(props.table.state.filters?.import ?? 'all'),
        idempotency: String(props.table.state.filters?.idempotency ?? 'all'),
        handling: String(props.table.state.filters?.handling ?? 'all'),
        from: String(props.table.state.filters?.from ?? ''),
        to: String(props.table.state.filters?.to ?? ''),
    };
}

function progressLabel(run: ManagedProcessRunRow): string {
    if (run.progressTotal !== null && run.progressTotal > 0) {
        return `${run.progressCurrent}/${run.progressTotal}`;
    }

    return run.progressLabel ?? t('pages.admin.managed_processes.progress_unknown');
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    return new Promise((resolve) => {
        router.post(
            '/admin/managed-processes/acknowledge',
            { runs: payload.rowIds },
            {
                preserveScroll: true,
                onFinish: () => resolve(),
            },
        );
    });
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.head_title')" />
    <ManagedProcessArea :title="t('pages.admin.managed_processes.title')" current-path="/admin/managed-processes">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.runs.metric.active')"
                    :value="summary.active ?? 0"
                    :icon="IconProgress"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.runs.metric.failed_24h')"
                    :value="summary.failed24h ?? 0"
                    :icon="IconAlertTriangle"
                    tone="rose"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.runs.metric.warnings_24h')"
                    :value="summary.warnings24h ?? 0"
                    :icon="IconAlertTriangle"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.runs.metric.handled')"
                    :value="summary.handled ?? 0"
                    :icon="IconCircleCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.imports.metric.import_runs')"
                    :value="summary.imports ?? 0"
                    :icon="IconDatabaseImport"
                    tone="teal"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.managed_processes.runs.filters')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.process" :label="t('pages.admin.managed_processes.process')" :options="processOptions" />
                    <FormSelect v-model="filters.status" :label="t('pages.admin.managed_processes.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.managed_processes.source')" :options="sourceOptions" />
                    <FormSelect v-model="filters.module" :label="t('pages.admin.managed_processes.module')" :options="moduleOptions" />
                    <FormSelect v-model="filters.import" :label="t('pages.admin.managed_processes.import')" :options="importOptions" />
                    <FormSelect
                        v-model="filters.idempotency"
                        :label="t('pages.admin.managed_processes.idempotency_state')"
                        :options="idempotencyOptions"
                    />
                    <FormSelect
                        v-model="filters.handling"
                        :label="t('pages.admin.managed_processes.handling_status')"
                        :options="handlingOptions"
                    />
                    <FormDateInput v-model="filters.from" :label="t('pages.admin.managed_processes.started_from')" :ui-locale="locale" />
                    <FormDateInput v-model="filters.to" :label="t('pages.admin.managed_processes.started_to')" :ui-locale="locale" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.managed_processes.runs.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.managed_processes.runs.empty')"
            />
        </PageStack>
    </ManagedProcessArea>
</template>
