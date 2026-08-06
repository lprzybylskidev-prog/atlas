<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCircleCheck, IconListDetails, IconRoute, IconServerCog } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';

interface FailedJobRow extends Record<string, unknown> {
    uuid: string;
    connection: string;
    queue: string;
    failedAt: string;
    displayName: string;
    jobClass: string;
    exceptionType: string;
    exceptionMessage: string;
    payload: string;
    exception: string;
    acknowledged: boolean;
    handlingStatus: 'needs_attention' | 'handled' | string;
    acknowledgedAt: string | null;
    acknowledgedBy: string | null;
}

interface QueueSummary {
    failedCount: number;
    handledCount: number;
    visibleCount: number;
    queues: number;
    latestFailedAt: string | null;
    oldestFailedAt: string | null;
}

interface KnownQueue {
    queue: string;
    configured: boolean;
    failedJobs: number;
    handledJobs: number;
}

interface QueueOperations {
    knownQueues: KnownQueue[];
    totalFailedJobs: number;
    totalHandledJobs: number;
}

interface QueueFilterOptions {
    connections: string[];
    queues: string[];
}

const props = defineProps<{
    jobs: FailedJobRow[];
    jobDetails: FailedJobRow[];
    summary: QueueSummary;
    queueOperations: QueueOperations;
    filterOptions: QueueFilterOptions;
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['connection', 'queue', 'handling', 'from', 'to'];
const filterDefaults = {
    connection: 'all',
    queue: 'all',
    handling: 'needs_attention',
    from: '',
    to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<FailedJobRow[]>(() => props.jobs.map((job) => displayJob(job)));
const detailRows = computed<FailedJobRow[]>(() => props.jobDetails.map((job) => displayJob(job)));
const columns = computed<DataTableColumn<FailedJobRow>[]>(() => [
    { key: 'uuid', label: t('pages.admin.queues.table.uuid') },
    { key: 'connection', label: t('pages.admin.queues.connection') },
    { key: 'queue', label: t('pages.admin.queues.queue') },
    { key: 'failedAt', label: t('pages.admin.queues.table.failed_at'), format: 'datetime' },
    { key: 'displayName', label: t('pages.admin.queues.table.job') },
    { key: 'jobClass', label: t('pages.admin.queues.job_class'), hidden: true },
    { key: 'exceptionType', label: t('pages.admin.queues.table.exception_type') },
    { key: 'exceptionMessage', label: t('pages.admin.queues.table.exception_message') },
    { key: 'handlingStatus', label: t('pages.admin.queues.table.handling_status'), format: 'status-badge' },
    { key: 'acknowledgedAt', label: t('pages.admin.queues.table.handled_at'), format: 'datetime', hidden: true },
    { key: 'acknowledgedBy', label: t('pages.admin.queues.table.handled_by'), hidden: true },
]);
const actions = computed<DataTableAction<FailedJobRow>[]>(() => [
    {
        key: 'retry',
        label: t('pages.admin.queues.retry'),
        method: 'post',
        href: (job) => `/admin/queues/failed-jobs/retry?uuids[]=${encodeURIComponent(job.uuid)}`,
        confirm: (job) => job.displayName,
        tone: 'warning',
    },
    {
        key: 'acknowledge',
        label: t('pages.admin.queues.acknowledge'),
        method: 'post',
        href: (job) => `/admin/queues/failed-jobs/acknowledge?uuids[]=${encodeURIComponent(job.uuid)}`,
        confirm: (job) => job.displayName,
        tone: 'success',
        visible: (job) => !job.acknowledged,
    },
]);
const bulkActions = computed<DataTableBulkAction[]>(() => [
    { key: 'retry', label: t('pages.admin.queues.retry_selected'), tone: 'warning' },
    { key: 'acknowledge', label: t('pages.admin.queues.acknowledge_selected'), tone: 'success' },
]);
const connectionOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.connections, t('pages.admin.queues.filters.any_connection'), connectionLabel),
);
const queueOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.queues, t('pages.admin.queues.filters.any_queue'), queueLabel),
);
const handlingOptions = computed<FormSelectOption[]>(() => [
    { value: 'needs_attention', label: t('pages.admin.queues.filters.needs_attention') },
    { value: 'handled', label: t('pages.admin.queues.filters.handled') },
    { value: 'all', label: t('pages.admin.queues.filters.any_handling') },
]);
const tableFilters = computed(() => filterValues());
const hasFailedJobs = computed(() => props.summary.failedCount > 0);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function displayJob(job: FailedJobRow): FailedJobRow {
    return {
        ...job,
        connection: connectionLabel(job.connection),
        queue: queueLabel(job.queue),
    };
}

function connectionLabel(value: string): string {
    const key = `pages.admin.queues.connections.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function queueLabel(value: string): string {
    const key = `pages.admin.queues.queues.${value.replaceAll('-', '_')}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function filterValues(): Record<string, string> {
    return {
        connection: String(props.table.state.filters?.connection ?? 'all'),
        queue: String(props.table.state.filters?.queue ?? 'all'),
        handling: String(props.table.state.filters?.handling ?? 'needs_attention'),
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

function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    const endpoint = payload.action.key === 'acknowledge' ? '/admin/queues/failed-jobs/acknowledge' : '/admin/queues/failed-jobs/retry';
    const data =
        payload.action.key === 'retry' && payload.rowIds.length > 1
            ? { uuids: payload.rowIds, confirmation: 'RETRY' }
            : { uuids: payload.rowIds };

    return new Promise((resolve) => {
        router.post(endpoint, data, {
            preserveScroll: true,
            onFinish: () => resolve(),
        });
    });
}
</script>

<template>
    <Head :title="t('pages.admin.queues.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.queues.title')" :title-icon="IconRoute">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <OperationalMetricTile
                    :label="t('pages.admin.queues.metric.failed_jobs')"
                    :value="summary.failedCount"
                    :icon="IconAlertTriangle"
                    :tone="hasFailedJobs ? 'rose' : 'emerald'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.queues.metric.handled_jobs')"
                    :value="summary.handledCount"
                    :icon="IconCircleCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.queues.metric.queues')"
                    :value="summary.queues"
                    :icon="IconRoute"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.queues.metric.visible_jobs')"
                    :value="summary.visibleCount"
                    :icon="IconListDetails"
                    tone="sky"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.queues.operations_snapshot')" :icon="IconServerCog" tone="sky">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="text-left text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                            <tr>
                                <th class="px-0 py-2 pr-3">{{ t('pages.admin.queues.queue') }}</th>
                                <th class="px-3 py-2">{{ t('pages.admin.queues.configured') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('pages.admin.queues.table.needs_attention') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('pages.admin.queues.table.handled') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="knownQueue in queueOperations.knownQueues" :key="knownQueue.queue">
                                <td class="px-0 py-2 pr-3 font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ queueLabel(knownQueue.queue) }}
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ knownQueue.configured ? t('datatable.boolean.yes') : t('datatable.boolean.no') }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-200">
                                    {{ knownQueue.failedJobs }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-zinc-700 dark:text-zinc-200">
                                    {{ knownQueue.handledJobs }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SurfaceCard>

            <FilterPanel
                :title="t('pages.admin.queues.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.connection" :label="t('pages.admin.queues.connection')" :options="connectionOptions" />
                    <FormSelect v-model="filters.queue" :label="t('pages.admin.queues.queue')" :options="queueOptions" />
                    <FormSelect v-model="filters.handling" :label="t('pages.admin.queues.filters.handling')" :options="handlingOptions" />
                    <FormDateInput v-model="filters.from" :label="t('pages.admin.queues.from_date')" :ui-locale="locale" />
                    <FormDateInput v-model="filters.to" :label="t('pages.admin.queues.to_date')" :ui-locale="locale" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.queues.failed_jobs')"
                :rows="rows"
                :columns="columns"
                row-key="uuid"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.queues.failed_jobs_empty')"
            />

            <SurfaceCard
                :title="t('pages.admin.queues.failed_job_details')"
                :subtitle="t('pages.admin.queues.failed_job_details_subtitle')"
                :icon="IconListDetails"
                tone="zinc"
            >
                <div v-if="detailRows.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('pages.admin.queues.failed_jobs_empty') }}
                </div>
                <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <details v-for="job in detailRows" :key="job.uuid" class="group py-3">
                        <summary
                            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 text-sm font-medium text-zinc-950 dark:text-zinc-50"
                        >
                            <span class="min-w-0 truncate">{{ job.displayName }} · {{ job.queue }}</span>
                            <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ job.uuid }}</span>
                        </summary>
                        <div class="mt-3 grid gap-3 xl:grid-cols-2">
                            <div>
                                <CodeViewer
                                    :title="t('pages.admin.queues.payload')"
                                    :content="job.payload"
                                    language="json"
                                    max-height="max-h-72"
                                />
                            </div>
                            <div>
                                <CodeViewer
                                    :title="t('pages.admin.queues.exception')"
                                    :content="job.exception"
                                    language="stack"
                                    max-height="max-h-72"
                                />
                            </div>
                        </div>
                    </details>
                </div>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
