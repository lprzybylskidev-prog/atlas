<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconFileImport } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
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

const { t } = useTranslator();
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
const summaryItems = computed(() => [
    { label: t('pages.admin.managed_processes.imports.metric.import_runs'), value: String(props.summary.imports), icon: IconFileImport },
]);

const columns: DataTableColumn<ImportExecution>[] = [
    { key: 'importKey', label: t('pages.admin.managed_processes.import') },
    { key: 'sourceType', label: t('pages.admin.managed_processes.source') },
    { key: 'status', label: t('pages.admin.managed_processes.status'), format: 'severity' },
    { key: 'idempotencyKey', label: t('pages.admin.managed_processes.idempotency_key') },
    { key: 'idempotencyState', label: t('pages.admin.managed_processes.idempotency_state') },
    { key: 'statistics', label: t('pages.admin.managed_processes.statistics') },
    { key: 'createdAt', label: t('pages.admin.managed_processes.created'), format: 'datetime' },
];
const actions: DataTableAction<ImportExecution>[] = [
    {
        key: 'open',
        label: t('pages.admin.managed_processes.open_logs'),
        href: (entry) => `/admin/managed-processes/${entry.runPublicId}`,
        tone: 'info',
    },
];

function optionList(values: string[]): { value: string; label: string }[] {
    return [
        { value: '', label: t('pages.admin.managed_processes.all') },
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
    <Head :title="t('pages.admin.managed_processes.imports.head_title')" />
    <AdminLayout
        :title="t('pages.admin.managed_processes.imports.title')"
        :title-icon="IconFileImport"
        :subnavigation="managedProcessSubnavigation('imports', t)"
        :subnavigation-label="t('pages.admin.managed_processes.nav.label')"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" />
            <NoticeBanner :title="t('pages.admin.managed_processes.bounded_title')">
                {{ t('pages.admin.managed_processes.bounded_imports') }}
            </NoticeBanner>
            <FilterPanel
                :title="t('pages.admin.managed_processes.imports.filters')"
                :summary="
                    t('pages.admin.managed_processes.imports.summary', {
                        visible: filteredRows.length,
                        total: props.importExecutions.length,
                    })
                "
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormSelect v-model="importFilter" :label="t('pages.admin.managed_processes.import')" :options="importOptions" />
                    <FormSelect v-model="statusFilter" :label="t('pages.admin.managed_processes.status')" :options="statusOptions" />
                    <FormSelect v-model="sourceFilter" :label="t('pages.admin.managed_processes.source')" :options="sourceOptions" />
                    <FormDateInput v-model="fromFilter" :label="t('pages.admin.managed_processes.created_from')" />
                    <FormDateInput v-model="toFilter" :label="t('pages.admin.managed_processes.created_to')" />
                </div>
            </FilterPanel>
            <DataTable
                :title="t('pages.admin.managed_processes.imports.title')"
                :rows="filteredRows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                state-key="admin.managed-processes.imports"
                export-key="admin.managed-processes.imports"
                :exports="exports"
                :filters="{ import: importFilter, status: statusFilter, source: sourceFilter, from: fromFilter, to: toFilter }"
                :empty-label="t('pages.admin.managed_processes.imports.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
