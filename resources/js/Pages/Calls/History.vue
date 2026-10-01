<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconArrowDownLeft, IconArrowUpRight, IconHistory, IconPhoneX, IconPhone } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import DataTable from '../../Components/DataTable.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../Types/data-table';

interface CallHistoryRow extends Record<string, unknown> {
    publicId: string;
    conversationLabel: string;
    conversationType: string;
    conversationTypeLabel: string;
    initialMode: string;
    initialModeLabel: string;
    direction: string;
    directionLabel: string;
    state: string;
    stateLabel: string;
    startedAt: string;
    duration: string;
    canRejoin: boolean;
}

const props = defineProps<{
    callRows: CallHistoryRow[];
    summary: { total: number; missed: number; incoming: number; outgoing: number };
    filter: 'all' | 'missed' | 'incoming' | 'outgoing';
    table: DataTableMeta;
}>();

const { t } = useTranslator();
const selectedFilter = ref(props.filter);
const filterOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('calls.filters.all') },
    { value: 'missed', label: t('calls.filters.missed') },
    { value: 'incoming', label: t('calls.filters.incoming') },
    { value: 'outgoing', label: t('calls.filters.outgoing') },
]);
const columns = computed<DataTableColumn<CallHistoryRow>[]>(() => [
    { key: 'publicId', label: t('calls.table.public_id'), access: 'forbidden' },
    { key: 'conversationLabel', label: t('calls.table.conversation') },
    { key: 'conversationType', label: t('calls.table.conversation_type'), access: 'forbidden' },
    { key: 'conversationTypeLabel', label: t('calls.table.conversation_type') },
    { key: 'initialMode', label: t('calls.table.initial_mode'), access: 'forbidden' },
    { key: 'initialModeLabel', label: t('calls.table.initial_mode') },
    { key: 'direction', label: t('calls.table.direction'), access: 'forbidden' },
    { key: 'directionLabel', label: t('calls.table.direction') },
    { key: 'state', label: t('calls.table.state'), access: 'forbidden' },
    { key: 'stateLabel', label: t('calls.table.state') },
    { key: 'startedAt', label: t('calls.table.started_at'), format: 'datetime' },
    { key: 'duration', label: t('calls.table.duration') },
    { key: 'canRejoin', label: t('calls.table.can_rejoin'), access: 'forbidden' },
]);
const actions = computed<DataTableAction<CallHistoryRow>[]>(() => [
    {
        key: 'rejoin',
        label: t('calls.actions.rejoin'),
        href: (row) => `/user/calls?rejoin=${encodeURIComponent(row.publicId)}`,
        method: 'get',
        tone: 'info',
        visible: (row) => row.canRejoin,
    },
]);

function applyFilter(): void {
    router.get('/user/calls', selectedFilter.value === 'all' ? {} : { call_filter: selectedFilter.value }, { preserveState: false });
}

function clearFilter(): void {
    selectedFilter.value = 'all';
    applyFilter();
}
</script>

<template>
    <Head :title="t('pages.calls.history.title')" />
    <AppLayout :title="t('pages.calls.history.title')" :title-icon="IconHistory" mode="user">
        <PageStack>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <OperationalMetricTile :label="t('calls.metrics.total')" :value="summary.total" :icon="IconPhone" tone="teal" />
                <OperationalMetricTile
                    :label="t('calls.metrics.missed')"
                    :value="summary.missed"
                    :icon="IconPhoneX"
                    :tone="summary.missed ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('calls.metrics.incoming')"
                    :value="summary.incoming"
                    :icon="IconArrowDownLeft"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('calls.metrics.outgoing')"
                    :value="summary.outgoing"
                    :icon="IconArrowUpRight"
                    tone="emerald"
                />
            </div>

            <FilterPanel
                :title="t('calls.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilter"
                @clear="clearFilter"
            >
                <div class="max-w-sm">
                    <FormSelect v-model="selectedFilter" :label="t('calls.filters.kind')" :options="filterOptions" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.calls.history.title')"
                :rows="callRows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
            />
        </PageStack>
    </AppLayout>
</template>
