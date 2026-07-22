<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconRotateClockwise, IconSettingsAutomation } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';
import { managedProcessSubnavigation } from './navigation';

interface Run extends Record<string, unknown> {
    publicId: string;
    processKey: string;
    moduleKey: string;
    status: string;
    sourceType: string;
    progressCurrent: number;
    progressTotal: number | null;
    progressLabel: string | null;
    actor: string | null;
    team: string | null;
    createdAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
}

const props = defineProps<{
    runs: Run[];
    summary: { active: number; failed24h: number; warnings24h: number; imports: number };
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator('en');
const processFilter = ref('');
const statusFilter = ref('');
const sourceFilter = ref('');
const moduleFilter = ref('');
const fromFilter = ref('');
const toFilter = ref('');

const summaryItems = computed<{ label: string; value: string; icon: Component; tone: string }[]>(() => [
    { label: 'Active runs', value: String(props.summary.active), icon: IconRotateClockwise, tone: 'sky' },
    {
        label: 'Failed 24h',
        value: String(props.summary.failed24h),
        icon: IconAlertTriangle,
        tone: props.summary.failed24h > 0 ? 'rose' : 'emerald',
    },
    {
        label: 'Warnings 24h',
        value: String(props.summary.warnings24h),
        icon: IconAlertTriangle,
        tone: props.summary.warnings24h > 0 ? 'amber' : 'emerald',
    },
]);

const processOptions = computed(() => optionList(props.runs.map((run) => run.processKey)));
const statusOptions = computed(() => optionList(props.runs.map((run) => run.status)));
const sourceOptions = computed(() => optionList(props.runs.map((run) => run.sourceType)));
const moduleOptions = computed(() => optionList(props.runs.map((run) => run.moduleKey)));
const filteredRuns = computed(() =>
    props.runs.filter(
        (run) =>
            matches(run.processKey, processFilter.value) &&
            matches(run.status, statusFilter.value) &&
            matches(run.sourceType, sourceFilter.value) &&
            matches(run.moduleKey, moduleFilter.value) &&
            matchesDateRange(run.startedAt ?? run.createdAt, fromFilter.value, toFilter.value),
    ),
);

const columns: DataTableColumn<Run>[] = [
    { key: 'processKey', label: 'Process' },
    { key: 'status', label: 'Status', format: 'severity' },
    { key: 'sourceType', label: 'Source' },
    { key: 'moduleKey', label: 'Module' },
    { key: 'progressLabel', label: 'Progress' },
    { key: 'progressCurrent', label: 'Done', format: 'number' },
    { key: 'progressTotal', label: 'Total', format: 'number' },
    { key: 'actor', label: 'Actor' },
    { key: 'team', label: 'Team' },
    { key: 'startedAt', label: 'Started', format: 'datetime' },
    { key: 'finishedAt', label: 'Finished', format: 'datetime' },
    { key: 'createdAt', label: 'Created', format: 'datetime', hidden: true },
];
const actions: DataTableAction<Run>[] = [
    { key: 'open', label: 'Open logs', href: (run) => `/admin/managed-processes/${run.publicId}`, tone: 'info' },
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
    processFilter.value = '';
    statusFilter.value = '';
    sourceFilter.value = '';
    moduleFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.head_title')" />
    <AdminLayout
        :title="t('pages.admin.managed_processes.title')"
        :title-icon="IconSettingsAutomation"
        :subnavigation="managedProcessSubnavigation('runs')"
        subnavigation-label="Managed process sections"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" columns="grid gap-3 sm:grid-cols-3" />
            <FilterPanel
                title="Run filters"
                :summary="`Showing ${filteredRuns.length} of ${props.runs.length} loaded process runs.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormSelect v-model="processFilter" label="Process" :options="processOptions" />
                    <FormSelect v-model="statusFilter" label="Status" :options="statusOptions" />
                    <FormSelect v-model="sourceFilter" label="Source" :options="sourceOptions" />
                    <FormSelect v-model="moduleFilter" label="Module" :options="moduleOptions" />
                    <FormDateInput v-model="fromFilter" label="Started from" />
                    <FormDateInput v-model="toFilter" label="Started to" />
                </div>
            </FilterPanel>
            <DataTable
                title="Process runs"
                :rows="filteredRuns"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                state-key="admin.managed-processes.runs"
                export-key="admin.managed-processes.runs"
                :exports="exports"
                :filters="{
                    process: processFilter,
                    status: statusFilter,
                    source: sourceFilter,
                    module: moduleFilter,
                    from: fromFilter,
                    to: toFilter,
                }"
                empty-label="No process runs match the current filters."
            />
        </PageStack>
    </AdminLayout>
</template>
