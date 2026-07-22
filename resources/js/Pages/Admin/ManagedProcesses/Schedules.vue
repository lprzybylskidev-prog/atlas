<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconCalendarTime, IconListDetails } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import SurfaceCard from '../../../Components/SurfaceCard.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import SectionHeader from '../../../Components/SectionHeader.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';
import { managedProcessSubnavigation } from './navigation';

interface Definition extends Record<string, unknown> {
    key: string;
    label: string;
}

interface Schedule extends Record<string, unknown> {
    publicId: string;
    processKey: string;
    moduleKey: string;
    team: string | null;
    timezone: string;
    cronExpression: string | null;
    intervalKey: string | null;
    enabled: boolean;
    nextDueAt: string | null;
    overlapPolicy: string;
    reason: string;
    createdAt: string | null;
}

const props = defineProps<{
    definitions: Definition[];
    schedules: Schedule[];
    summary: { schedules: number; disabled: number };
    exports: DataTableExportMeta;
}>();

const scheduleForm = useForm({
    process_key: props.definitions[0]?.key ?? '',
    cron_expression: '15 2 * * 1-5',
    reason: 'Operational schedule.',
});
const scheduleProcessFilter = ref('');
const scheduleModuleFilter = ref('');
const scheduleStateFilter = ref('');
const scheduleFromFilter = ref('');
const scheduleToFilter = ref('');

const summaryItems = computed<{ label: string; value: string; icon: Component; tone: string }[]>(() => [
    { label: 'Active schedules', value: String(props.summary.schedules), icon: IconCalendarTime, tone: 'sky' },
    { label: 'Disabled schedules', value: String(props.summary.disabled), icon: IconListDetails, tone: 'zinc' },
]);

const scheduleProcessOptions = computed(() => optionList(props.schedules.map((schedule) => schedule.processKey)));
const scheduleModuleOptions = computed(() => optionList(props.schedules.map((schedule) => schedule.moduleKey)));
const scheduleStateOptions = [
    { value: '', label: 'All' },
    { value: 'enabled', label: 'Enabled' },
    { value: 'disabled', label: 'Disabled' },
];
const processOptions = computed(() => props.definitions.map((definition) => ({ value: definition.key, label: definition.label })));

const filteredSchedules = computed(() =>
    props.schedules.filter(
        (schedule) =>
            matches(schedule.processKey, scheduleProcessFilter.value) &&
            matches(schedule.moduleKey, scheduleModuleFilter.value) &&
            (scheduleStateFilter.value === '' ||
                (scheduleStateFilter.value === 'enabled' && schedule.enabled) ||
                (scheduleStateFilter.value === 'disabled' && !schedule.enabled)) &&
            matchesDateRange(schedule.nextDueAt, scheduleFromFilter.value, scheduleToFilter.value),
    ),
);

const scheduleColumns: DataTableColumn<Schedule>[] = [
    { key: 'processKey', label: 'Process' },
    { key: 'moduleKey', label: 'Module' },
    { key: 'team', label: 'Team' },
    { key: 'cronExpression', label: 'Cron' },
    { key: 'enabled', label: 'Enabled', format: 'boolean' },
    { key: 'nextDueAt', label: 'Next due', format: 'datetime' },
    { key: 'createdAt', label: 'Created', format: 'datetime' },
    { key: 'overlapPolicy', label: 'Overlap' },
    { key: 'reason', label: 'Reason' },
];

const scheduleActions: DataTableAction<Schedule>[] = [
    {
        key: 'deactivate',
        label: 'Disable',
        method: 'patch',
        href: (schedule) => `/admin/managed-processes/schedules/${schedule.publicId}/disable`,
        visible: (schedule) => schedule.enabled,
        tone: 'warning',
    },
];

function optionList(values: string[]): { value: string; label: string }[] {
    return [
        { value: '', label: 'All' },
        ...Array.from(new Set(values.filter((value) => value !== '')))
            .sort((first, second) => first.localeCompare(second))
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

    const timestamp = new Date(value).getTime();

    if (Number.isNaN(timestamp)) {
        return true;
    }

    if (from !== '' && timestamp < new Date(`${from}T00:00:00`).getTime()) {
        return false;
    }

    if (to !== '' && timestamp > new Date(`${to}T23:59:59`).getTime()) {
        return false;
    }

    return true;
}

function resetScheduleFilters(): void {
    scheduleProcessFilter.value = '';
    scheduleModuleFilter.value = '';
    scheduleStateFilter.value = '';
    scheduleFromFilter.value = '';
    scheduleToFilter.value = '';
}

function createSchedule(): void {
    scheduleForm.post('/admin/managed-processes/schedules', {
        preserveScroll: true,
        onSuccess: () => {
            scheduleForm.reset('reason');
            scheduleForm.cron_expression = '15 2 * * 1-5';
            scheduleForm.reason = 'Operational schedule.';
        },
    });
}
</script>

<template>
    <Head title="Managed process schedules" />
    <AdminLayout
        title="Schedules"
        :title-icon="IconCalendarTime"
        :subnavigation="managedProcessSubnavigation('schedules')"
        subnavigation-label="Managed process sections"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" columns="grid gap-3 sm:grid-cols-2" />

            <SurfaceCard
                title="Create schedule"
                :icon="IconCalendarTime"
                subtitle="Register a five-field cron schedule for a process definition that explicitly supports scheduling."
            >
                <AtlasForm
                    class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(12rem,0.7fr)_minmax(0,1.4fr)_auto] xl:items-end"
                    :processing="scheduleForm.processing"
                    @submit="createSchedule"
                >
                    <FormSelect
                        v-model="scheduleForm.process_key"
                        label="Process"
                        :options="processOptions"
                        :error="scheduleForm.errors.process_key"
                    />
                    <FormInput
                        v-model="scheduleForm.cron_expression"
                        label="Cron expression"
                        placeholder="15 2 * * 1-5"
                        monospace
                        :error="scheduleForm.errors.cron_expression"
                    />
                    <FormInput v-model="scheduleForm.reason" label="Reason" :error="scheduleForm.errors.reason" />
                    <FormButton
                        type="submit"
                        :icon="IconCalendarTime"
                        :loading="scheduleForm.processing"
                        :disabled="!scheduleForm.process_key || !scheduleForm.cron_expression.trim() || !scheduleForm.reason.trim()"
                    >
                        Create
                    </FormButton>
                </AtlasForm>
            </SurfaceCard>

            <section class="space-y-3">
                <SectionHeader title="Schedule entries" :icon="IconListDetails" />
                <FilterPanel
                    title="Schedule filters"
                    :summary="`Showing ${filteredSchedules.length} of ${props.schedules.length} schedule entries.`"
                    @apply="() => {}"
                    @clear="resetScheduleFilters"
                >
                    <div
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                    >
                        <FormSelect v-model="scheduleProcessFilter" label="Process" :options="scheduleProcessOptions" />
                        <FormSelect v-model="scheduleModuleFilter" label="Module" :options="scheduleModuleOptions" />
                        <FormSelect v-model="scheduleStateFilter" label="State" :options="scheduleStateOptions" />
                        <FormDateInput v-model="scheduleFromFilter" label="Due from" />
                        <FormDateInput v-model="scheduleToFilter" label="Due to" />
                    </div>
                </FilterPanel>
                <DataTable
                    title="Schedules"
                    :rows="filteredSchedules"
                    :columns="scheduleColumns"
                    row-key="publicId"
                    :actions="scheduleActions"
                    state-key="admin.managed-processes.schedules"
                    export-key="admin.managed-processes.schedules"
                    :exports="exports"
                    :filters="{
                        process: scheduleProcessFilter,
                        module: scheduleModuleFilter,
                        state: scheduleStateFilter,
                        from: scheduleFromFilter,
                        to: scheduleToFilter,
                    }"
                    empty-label="No managed process schedules match the current filters."
                />
            </section>
        </PageStack>
    </AdminLayout>
</template>
