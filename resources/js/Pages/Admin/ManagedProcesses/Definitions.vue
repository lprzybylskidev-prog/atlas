<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconListDetails } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableAction, DataTableColumn } from '../../../Types/data-table';
import ManagedProcessTabs from './Partials/ManagedProcessTabs.vue';

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
}>();

const moduleFilter = ref('');
const scheduleFilter = ref('');
const manualFilter = ref('');

const moduleOptions = computed(() => optionList(props.definitions.map((definition) => definition.moduleKey)));
const booleanOptions = [
    { value: '', label: 'All' },
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
];
const filteredRows = computed(() =>
    props.definitions.filter(
        (definition) =>
            matches(definition.moduleKey, moduleFilter.value) &&
            matchesBoolean(definition.scheduleSupported, scheduleFilter.value) &&
            matchesBoolean(definition.manualStartSupported, manualFilter.value),
    ),
);

const summaryItems = computed<{ label: string; value: string; icon: Component }[]>(() => [
    { label: 'Definitions', value: String(props.summary.definitions), icon: IconListDetails },
    { label: 'Schedulable', value: String(props.summary.schedulable), icon: IconListDetails },
    { label: 'Manual start', value: String(props.summary.manual), icon: IconListDetails },
]);
const columns: DataTableColumn<Definition>[] = [
    { key: 'label', label: 'Process' },
    { key: 'key', label: 'Key' },
    { key: 'moduleKey', label: 'Module' },
    { key: 'scope', label: 'Scope' },
    { key: 'queueName', label: 'Queue' },
    { key: 'executionMode', label: 'Mode' },
    { key: 'concurrencyPolicy', label: 'Concurrency' },
    { key: 'retryable', label: 'Retry', format: 'boolean' },
    { key: 'scheduleSupported', label: 'Schedule', format: 'boolean' },
];
const actions: DataTableAction<Definition>[] = [
    {
        key: 'run',
        label: 'Run',
        method: 'post',
        href: (definition) => `/admin/managed-processes/definitions/${encodeURIComponent(definition.key)}/run`,
        visible: (definition) => definition.manualStartSupported,
        tone: 'success',
    },
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
    <Head title="Process definitions" />
    <AdminLayout title="Process definitions" :title-icon="IconListDetails">
        <section class="space-y-5">
            <ManagedProcessTabs active="definitions" />
            <MetricGrid :items="summaryItems" columns="grid gap-3 sm:grid-cols-3" />
            <FilterPanel
                title="Definition filters"
                :summary="`Showing ${filteredRows.length} of ${props.definitions.length} registered definitions.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormSelect v-model="moduleFilter" label="Module" :options="moduleOptions" />
                    <FormSelect v-model="scheduleFilter" label="Schedule support" :options="booleanOptions" />
                    <FormSelect v-model="manualFilter" label="Manual start" :options="booleanOptions" />
                </div>
            </FilterPanel>
            <DataTable
                title="Registered definitions"
                :rows="filteredRows"
                :columns="columns"
                row-key="key"
                :actions="actions"
                state-key="admin.managed-processes.definitions"
                empty-label="No definitions match the current filters."
            />
        </section>
    </AdminLayout>
</template>
