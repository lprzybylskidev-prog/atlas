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
import { useTranslator } from '../../../Localization/translator';
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

const { t } = useTranslator();
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
    {
        label: t('pages.admin.managed_processes.schedules.metric.active'),
        value: String(props.summary.schedules),
        icon: IconCalendarTime,
        tone: 'sky',
    },
    {
        label: t('pages.admin.managed_processes.schedules.metric.disabled'),
        value: String(props.summary.disabled),
        icon: IconListDetails,
        tone: 'zinc',
    },
]);

const scheduleProcessOptions = computed(() => optionList(props.schedules.map((schedule) => schedule.processKey)));
const scheduleModuleOptions = computed(() => optionList(props.schedules.map((schedule) => schedule.moduleKey)));
const scheduleStateOptions = [
    { value: '', label: t('pages.admin.managed_processes.all') },
    { value: 'enabled', label: t('pages.admin.managed_processes.enabled') },
    { value: 'disabled', label: t('pages.admin.managed_processes.disabled') },
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
    { key: 'processKey', label: t('pages.admin.managed_processes.process') },
    { key: 'moduleKey', label: t('pages.admin.managed_processes.module') },
    { key: 'team', label: t('pages.admin.managed_processes.team') },
    { key: 'cronExpression', label: t('pages.admin.managed_processes.cron') },
    { key: 'enabled', label: t('pages.admin.managed_processes.enabled'), format: 'boolean' },
    { key: 'nextDueAt', label: t('pages.admin.managed_processes.next_due'), format: 'datetime' },
    { key: 'createdAt', label: t('pages.admin.managed_processes.created'), format: 'datetime' },
    { key: 'overlapPolicy', label: t('pages.admin.managed_processes.overlap') },
    { key: 'reason', label: t('pages.admin.managed_processes.reason') },
];

const scheduleActions: DataTableAction<Schedule>[] = [
    {
        key: 'deactivate',
        label: t('pages.admin.managed_processes.disable'),
        method: 'patch',
        href: (schedule) => `/admin/managed-processes/schedules/${schedule.publicId}/disable`,
        visible: (schedule) => schedule.enabled,
        tone: 'warning',
    },
];

function optionList(values: string[]): { value: string; label: string }[] {
    return [
        { value: '', label: t('pages.admin.managed_processes.all') },
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
    <Head :title="t('pages.admin.managed_processes.schedules.head_title')" />
    <AdminLayout
        :title="t('pages.admin.managed_processes.schedules.title')"
        :title-icon="IconCalendarTime"
        :subnavigation="managedProcessSubnavigation('schedules', t)"
        :subnavigation-label="t('pages.admin.managed_processes.nav.label')"
    >
        <PageStack>
            <MetricGrid :items="summaryItems" columns="grid gap-3 sm:grid-cols-2" />

            <SurfaceCard
                :title="t('pages.admin.managed_processes.schedules.create')"
                :icon="IconCalendarTime"
                :subtitle="t('pages.admin.managed_processes.schedules.create_subtitle')"
            >
                <AtlasForm
                    class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(12rem,0.7fr)_minmax(0,1.4fr)_auto] xl:items-end"
                    :processing="scheduleForm.processing"
                    @submit="createSchedule"
                >
                    <FormSelect
                        v-model="scheduleForm.process_key"
                        :label="t('pages.admin.managed_processes.process')"
                        :options="processOptions"
                        :error="scheduleForm.errors.process_key"
                    />
                    <FormInput
                        v-model="scheduleForm.cron_expression"
                        :label="t('pages.admin.managed_processes.cron_expression')"
                        placeholder="15 2 * * 1-5"
                        monospace
                        :error="scheduleForm.errors.cron_expression"
                    />
                    <FormInput
                        v-model="scheduleForm.reason"
                        :label="t('pages.admin.managed_processes.reason')"
                        :error="scheduleForm.errors.reason"
                    />
                    <FormButton
                        type="submit"
                        :icon="IconCalendarTime"
                        :loading="scheduleForm.processing"
                        :disabled="!scheduleForm.process_key || !scheduleForm.cron_expression.trim() || !scheduleForm.reason.trim()"
                    >
                        {{ t('pages.admin.managed_processes.create') }}
                    </FormButton>
                </AtlasForm>
            </SurfaceCard>

            <section class="space-y-3">
                <SectionHeader :title="t('pages.admin.managed_processes.schedules.entries')" :icon="IconListDetails" />
                <FilterPanel
                    :title="t('pages.admin.managed_processes.schedules.filters')"
                    :summary="
                        t('pages.admin.managed_processes.schedules.summary', {
                            visible: filteredSchedules.length,
                            total: props.schedules.length,
                        })
                    "
                    @apply="() => {}"
                    @clear="resetScheduleFilters"
                >
                    <div
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                    >
                        <FormSelect
                            v-model="scheduleProcessFilter"
                            :label="t('pages.admin.managed_processes.process')"
                            :options="scheduleProcessOptions"
                        />
                        <FormSelect
                            v-model="scheduleModuleFilter"
                            :label="t('pages.admin.managed_processes.module')"
                            :options="scheduleModuleOptions"
                        />
                        <FormSelect
                            v-model="scheduleStateFilter"
                            :label="t('pages.admin.managed_processes.state')"
                            :options="scheduleStateOptions"
                        />
                        <FormDateInput v-model="scheduleFromFilter" :label="t('pages.admin.managed_processes.due_from')" />
                        <FormDateInput v-model="scheduleToFilter" :label="t('pages.admin.managed_processes.due_to')" />
                    </div>
                </FilterPanel>
                <DataTable
                    :title="t('pages.admin.managed_processes.schedules.title')"
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
                    :empty-label="t('pages.admin.managed_processes.schedules.empty')"
                />
            </section>
        </PageStack>
    </AdminLayout>
</template>
