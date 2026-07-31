<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconCalendarPlus, IconCalendarTime, IconPlayerPause } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import ManagedProcessArea from '../../../Components/ManagedProcesses/ManagedProcessArea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { allOptions, yesNoOptions } from '../../../Composables/useManagedProcessUi';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ManagedProcessFilterOptions, ManagedProcessScheduleRow, ManagedProcessSummary } from '../../../Types/managed-processes';

const props = defineProps<{
    schedules: ManagedProcessScheduleRow[];
    summary: ManagedProcessSummary;
    filterOptions: ManagedProcessFilterOptions;
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['process', 'enabled', 'module', 'from', 'to'];
const filterDefaults = {
    process: 'all',
    enabled: 'all',
    module: 'all',
    from: '',
    to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const columns = computed<DataTableColumn<ManagedProcessScheduleRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.managed_processes.table.public_id'), hidden: true },
    { key: 'processKey', label: t('pages.admin.managed_processes.process') },
    { key: 'moduleKey', label: t('pages.admin.managed_processes.module') },
    { key: 'team', label: t('pages.admin.managed_processes.team') },
    { key: 'cronExpression', label: t('pages.admin.managed_processes.cron') },
    { key: 'enabled', label: t('pages.admin.managed_processes.state'), format: 'boolean' },
    { key: 'nextDueAt', label: t('pages.admin.managed_processes.next_due'), format: 'datetime' },
    { key: 'createdAt', label: t('pages.admin.managed_processes.created'), format: 'datetime' },
    { key: 'overlapPolicy', label: t('pages.admin.managed_processes.overlap') },
    { key: 'reason', label: t('pages.admin.managed_processes.reason') },
    { key: 'scope', label: t('pages.admin.managed_processes.scope'), hidden: true },
    { key: 'timezone', label: t('pages.admin.managed_processes.table.timezone'), hidden: true },
    { key: 'intervalKey', label: t('pages.admin.managed_processes.table.interval'), hidden: true },
]);
const actions = computed<DataTableAction<ManagedProcessScheduleRow>[]>(() => [
    {
        key: 'disable',
        label: t('pages.admin.managed_processes.disable'),
        method: 'patch',
        href: (schedule) => `/admin/managed-processes/schedules/${encodeURIComponent(schedule.publicId)}/disable`,
        tone: 'warning',
        disabled: (schedule) => schedule.enabled !== true,
        disabledReason: t('pages.admin.managed_processes.action_disabled.schedule_already_disabled'),
    },
]);
const processOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.processes ?? [], t('pages.admin.managed_processes.filters.any_process')),
);
const moduleOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.modules ?? [], t('pages.admin.managed_processes.filters.any_module')),
);
const enabledOptions = computed<FormSelectOption[]>(() => yesNoOptions(t));
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
        enabled: String(props.table.state.filters?.enabled ?? 'all'),
        module: String(props.table.state.filters?.module ?? 'all'),
        from: String(props.table.state.filters?.from ?? ''),
        to: String(props.table.state.filters?.to ?? ''),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.schedules.head_title')" />
    <ManagedProcessArea :title="t('pages.admin.managed_processes.schedules.title')" current-path="/admin/managed-processes/schedules">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2">
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.schedules.metric.active')"
                    :value="summary.schedules ?? 0"
                    :icon="IconCalendarTime"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.schedules.metric.disabled')"
                    :value="summary.disabled ?? 0"
                    :icon="IconPlayerPause"
                    tone="zinc"
                />
            </div>

            <div class="flex justify-end">
                <ActionLink href="/admin/managed-processes/schedules/create" :icon="IconCalendarPlus" tone="primary">
                    {{ t('pages.admin.managed_processes.schedules.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.managed_processes.schedules.filters')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.process" :label="t('pages.admin.managed_processes.process')" :options="processOptions" />
                    <FormSelect v-model="filters.enabled" :label="t('pages.admin.managed_processes.state')" :options="enabledOptions" />
                    <FormSelect v-model="filters.module" :label="t('pages.admin.managed_processes.module')" :options="moduleOptions" />
                    <FormDateInput v-model="filters.from" :label="t('pages.admin.managed_processes.due_from')" :ui-locale="locale" />
                    <FormDateInput v-model="filters.to" :label="t('pages.admin.managed_processes.due_to')" :ui-locale="locale" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.managed_processes.schedules.entries')"
                :rows="schedules"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.managed_processes.schedules.empty')"
            />
        </PageStack>
    </ManagedProcessArea>
</template>
