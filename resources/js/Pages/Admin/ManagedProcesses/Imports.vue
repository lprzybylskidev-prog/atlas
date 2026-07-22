<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconFileImport } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';
import { managedProcessSubnavigation } from './navigation';

interface ImportExecution extends Record<string, unknown> {
    publicId: string;
    runPublicId: string;
    importKey: string;
    sourceType: string;
    status: string;
    statistics: string;
    idempotencyKey: string | null;
    idempotencyState: string;
    createdAt: string | null;
}

const props = defineProps<{
    importExecutions: ImportExecution[];
    summary: { imports: number };
    exports: DataTableExportMeta;
}>();

const importFilter = ref('');
const statusFilter = ref('');
const sourceFilter = ref('');
const fromFilter = ref('');
const toFilter = ref('');

const importOptions = computed(() => optionList(props.importExecutions.map((entry) => entry.importKey)));
const statusOptions = computed(() => optionList(props.importExecutions.map((entry) => entry.status)));
const sourceOptions = computed(() => optionList(props.importExecutions.map((entry) => entry.sourceType)));
const filteredRows = computed(() =>
    props.importExecutions.filter(
        (entry) =>
            matches(entry.importKey, importFilter.value) &&
            matches(entry.status, statusFilter.value) &&
            matches(entry.sourceType, sourceFilter.value) &&
            matchesDateRange(entry.createdAt, fromFilter.value, toFilter.value),
    ),
);
const summaryItems = computed(() => [{ label: 'Import runs', value: String(props.summary.imports), icon: IconFileImport }]);

const columns: DataTableColumn<ImportExecution>[] = [
    { key: 'importKey', label: 'Import' },
    { key: 'sourceType', label: 'Source' },
    { key: 'status', label: 'Status', format: 'severity' },
    { key: 'idempotencyKey', label: 'Idempotency key' },
    { key: 'idempotencyState', label: 'Idempotency state' },
    { key: 'statistics', label: 'Statistics' },
    { key: 'createdAt', label: 'Created', format: 'datetime' },
];
const actions: DataTableAction<ImportExecution>[] = [
    { key: 'open', label: 'Open logs', href: (entry) => `/admin/managed-processes/${entry.runPublicId}`, tone: 'info' },
];

function optionList(values: string[]): { value: string; label: string }[] {
    return [
        { value: '', label: 'All' },
        ...Array.from(new Set(values))
            .sort()
            .map((value) => ({ value, label: value })),
    ];
}

function matches(value: string, filter: string): boolean {
    return filter === '' || value === filter;
}

function matchesDateRange(value: string | null, from: string, to: string): boolean {
    if (value === null) {
        return from === '' && to === '';
    }

    const timestamp = Date.parse(value.includes('T') ? value : value.replace(' ', 'T'));

    return (from === '' || timestamp >= Date.parse(`${from}T00:00:00`)) && (to === '' || timestamp <= Date.parse(`${to}T23:59:59.999`));
}

function resetFilters(): void {
    importFilter.value = '';
    statusFilter.value = '';
    sourceFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
}
</script>

<template>
    <Head title="Import executions" />
    <AdminLayout
        title="Import executions"
        :title-icon="IconFileImport"
        :subnavigation="managedProcessSubnavigation('imports')"
        subnavigation-label="Managed process sections"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" />
            <FilterPanel
                title="Import filters"
                :summary="`Showing ${filteredRows.length} of ${props.importExecutions.length} loaded import executions.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormSelect v-model="importFilter" label="Import" :options="importOptions" />
                    <FormSelect v-model="statusFilter" label="Status" :options="statusOptions" />
                    <FormSelect v-model="sourceFilter" label="Source" :options="sourceOptions" />
                    <FormDateInput v-model="fromFilter" label="Created from" />
                    <FormDateInput v-model="toFilter" label="Created to" />
                </div>
            </FilterPanel>
            <DataTable
                title="Import executions"
                :rows="filteredRows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                state-key="admin.managed-processes.imports"
                export-key="admin.managed-processes.imports"
                :exports="exports"
                :filters="{ import: importFilter, status: statusFilter, source: sourceFilter, from: fromFilter, to: toFilter }"
                empty-label="No import executions match the current filters."
            />
        </PageStack>
    </AdminLayout>
</template>
