<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconListDetails } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';
import { managedProcessSubnavigation } from './navigation';

interface Definition extends Record<string, unknown> {
    key: string;
    moduleKey: string;
    label: string;
    scope: string;
    queueName: string;
    executionMode: string;
    concurrencyPolicy: string;
    retryable: boolean;
    scheduleSupported: boolean;
    manualStartSupported: boolean;
}

const props = defineProps<{
    definitions: Definition[];
    summary: { definitions: number; schedulable: number; manual: number };
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator();
const moduleFilter = ref('');
const scheduleFilter = ref('');
const manualFilter = ref('');

const moduleOptions = computed(() => optionList(props.definitions.map((definition) => definition.moduleKey)));
const booleanOptions = computed(() => [
    { value: '', label: t('pages.admin.managed_processes.all') },
    { value: 'yes', label: t('datatable.boolean.yes') },
    { value: 'no', label: t('datatable.boolean.no') },
]);
const filteredRows = computed(() =>
    props.definitions.filter(
        (definition) =>
            matches(definition.moduleKey, moduleFilter.value) &&
            matchesBoolean(definition.scheduleSupported, scheduleFilter.value) &&
            matchesBoolean(definition.manualStartSupported, manualFilter.value),
    ),
);

const summaryItems = computed<{ label: string; value: string; icon: Component }[]>(() => [
    {
        label: t('pages.admin.managed_processes.definitions.metric.definitions'),
        value: String(props.summary.definitions),
        icon: IconListDetails,
    },
    {
        label: t('pages.admin.managed_processes.definitions.metric.schedulable'),
        value: String(props.summary.schedulable),
        icon: IconListDetails,
    },
    { label: t('pages.admin.managed_processes.definitions.metric.manual'), value: String(props.summary.manual), icon: IconListDetails },
]);
const columns: DataTableColumn<Definition>[] = [
    { key: 'label', label: t('pages.admin.managed_processes.process') },
    { key: 'key', label: t('pages.admin.managed_processes.key') },
    { key: 'moduleKey', label: t('pages.admin.managed_processes.module') },
    { key: 'scope', label: t('pages.admin.managed_processes.scope') },
    { key: 'queueName', label: t('pages.admin.managed_processes.queue') },
    { key: 'executionMode', label: t('pages.admin.managed_processes.mode') },
    { key: 'concurrencyPolicy', label: t('pages.admin.managed_processes.concurrency') },
    { key: 'retryable', label: t('pages.admin.managed_processes.retry'), format: 'boolean' },
    { key: 'scheduleSupported', label: t('pages.admin.managed_processes.schedule'), format: 'boolean' },
];
const actions: DataTableAction<Definition>[] = [
    {
        key: 'run',
        label: t('pages.admin.managed_processes.run'),
        method: 'post',
        href: (definition) => `/admin/managed-processes/definitions/${encodeURIComponent(definition.key)}/run`,
        visible: (definition) => definition.manualStartSupported,
        tone: 'success',
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

function matchesBoolean(value: boolean, filter: string): boolean {
    return filter === '' || (filter === 'yes' && value) || (filter === 'no' && !value);
}

function resetFilters(): void {
    moduleFilter.value = '';
    scheduleFilter.value = '';
    manualFilter.value = '';
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.definitions.head_title')" />
    <AdminLayout
        :title="t('pages.admin.managed_processes.definitions.title')"
        :title-icon="IconListDetails"
        :subnavigation="managedProcessSubnavigation('definitions', t)"
        :subnavigation-label="t('pages.admin.managed_processes.nav.label')"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" columns="grid gap-3 sm:grid-cols-3" />
            <FilterPanel
                :title="t('pages.admin.managed_processes.definitions.filters')"
                :summary="
                    t('pages.admin.managed_processes.definitions.summary', {
                        visible: filteredRows.length,
                        total: props.definitions.length,
                    })
                "
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormSelect v-model="moduleFilter" :label="t('pages.admin.managed_processes.module')" :options="moduleOptions" />
                    <FormSelect
                        v-model="scheduleFilter"
                        :label="t('pages.admin.managed_processes.schedule_support')"
                        :options="booleanOptions"
                    />
                    <FormSelect v-model="manualFilter" :label="t('pages.admin.managed_processes.manual_start')" :options="booleanOptions" />
                </div>
            </FilterPanel>
            <DataTable
                :title="t('pages.admin.managed_processes.definitions.registered')"
                :rows="filteredRows"
                :columns="columns"
                row-key="key"
                :actions="actions"
                state-key="admin.managed-processes.definitions"
                export-key="admin.managed-processes.definitions"
                :exports="exports"
                :filters="{ module: moduleFilter, schedule: scheduleFilter, manual: manualFilter }"
                :empty-label="t('pages.admin.managed_processes.definitions.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
