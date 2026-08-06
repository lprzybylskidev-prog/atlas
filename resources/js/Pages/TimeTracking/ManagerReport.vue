<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconClockHour4,
    IconFilePencil,
    IconHourglass,
    IconPlayerPause,
    IconRefresh,
    IconUserMinus,
    IconUsersGroup,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import AtlasBarChart from '../../Components/AtlasBarChart.vue';
import DataTable from '../../Components/DataTable.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import UiState from '../../Components/UiState.vue';
import {
    formatTimeTrackingDuration,
    localizedTimeTrackingComparisonMetrics,
    timeTrackingCompareOptions,
    timeTrackingComparisonChart,
    timeTrackingContextLabel,
    timeTrackingDistributionChart,
    timeTrackingRangeOptions,
    timeTrackingStatusLabel,
    timeTrackingStatusOptions,
    timeTrackingTypeLabel,
    timeTrackingTypeOptions,
} from '../../Composables/useTimeTrackingReportUi';
import { applyTableFilters, clearTableFilters } from '../../Composables/useTableFilterControls';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../Types/data-table';
import { formatDateTime } from '../../Utils/formatters';

interface TimeReportRow extends Record<string, unknown> {
    publicId: string;
    userPublicId: string;
    userName: string;
    userEmail: string;
    type: string;
    rawType?: string;
    status: string;
    context: string;
    startedAt: string;
    endedAt: string;
    duration?: string;
    exactSeconds: number;
    reason: string;
    breakLimitStatus?: string;
    excessBreakSeconds?: number;
}

interface TimeReportSummary {
    totalSeconds: number;
    workSeconds: number;
    breakSeconds: number;
    otherWorkSeconds: number;
    corrections: number;
    pending: number;
}

interface ComparisonMetric {
    metric: string;
    currentSeconds: number;
    previousSeconds: number;
    deltaSeconds: number;
    percentDelta: number | null;
}

interface ComparisonUserMetric extends ComparisonMetric {
    userPublicId: string;
    userName: string;
}

interface TimeReportComparison {
    available: boolean;
    rangeLabel: string;
    previousRangeLabel: string;
    metrics: ComparisonMetric[];
    userMetrics: ComparisonUserMetric[];
}

interface TeamSummary {
    visibleUsers: number;
    working: number;
    break: number;
    otherWork: number;
    noSession: number;
}

interface StatusFeedItem {
    publicId: string;
    userName: string;
    userEmail: string;
    type: string;
    status: string;
    occurredAt: string;
    context: string;
}

const props = defineProps<{
    rows: TimeReportRow[];
    summary: TimeReportSummary;
    comparison: TimeReportComparison | null;
    filters: Record<string, string>;
    filterOptions: {
        modules: string[];
    };
    scope: {
        headManager: boolean;
        visibleUsers: number;
    };
    teamSummary: TeamSummary;
    statusFeed: StatusFeedItem[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['range', 'from', 'to', 'type', 'status', 'module', 'compare'];
const filterDefaults = {
    range: 'settlement_period',
    from: '',
    to: '',
    type: 'all',
    status: 'all',
    module: '',
    compare: 'off',
};
const filters = ref({ ...filterDefaults, ...props.filters });

const columns = computed<DataTableColumn<TimeReportRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.table.public_id'), hidden: true },
    { key: 'userPublicId', label: t('pages.time_tracking.manager_report.table.user_public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.manager_report.table.user') },
    { key: 'userEmail', label: t('pages.time_tracking.manager_report.table.user_email'), hidden: true },
    { key: 'type', label: t('pages.time_tracking.user_report.table.type'), format: 'status' },
    { key: 'status', label: t('pages.time_tracking.user_report.table.status'), format: 'status-badge' },
    { key: 'context', label: t('pages.time_tracking.user_report.table.context') },
    { key: 'startedAt', label: t('pages.time_tracking.user_report.table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.user_report.table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.user_report.table.duration') },
    { key: 'exactSeconds', label: t('pages.time_tracking.user_report.table.exact_seconds'), format: 'number', hidden: true },
    { key: 'reason', label: t('pages.time_tracking.user_report.table.reason'), hidden: true },
]);

const rangeOptions = computed<FormSelectOption[]>(() => timeTrackingRangeOptions(t));
const typeOptions = computed<FormSelectOption[]>(() => timeTrackingTypeOptions(t));
const statusOptions = computed<FormSelectOption[]>(() => timeTrackingStatusOptions(t));
const moduleOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.user_report.filters.any_module') },
    ...props.filterOptions.modules.map((module) => ({ value: module, label: contextLabel(module) })),
]);
const compareOptions = computed<FormSelectOption[]>(() => timeTrackingCompareOptions(t));
const localizedRows = computed<TimeReportRow[]>(() =>
    props.rows.map((row) => ({
        ...row,
        rawType: row.type,
        type: typeLabel(row.type),
        status: row.status,
        context: contextLabel(row.context),
        duration: formatDuration(row.exactSeconds),
    })),
);
const scopeLabel = computed(() =>
    props.scope.headManager ? t('pages.time_tracking.manager_report.scope.head') : t('pages.time_tracking.manager_report.scope.direct'),
);
const localizedStatusFeed = computed(() =>
    props.statusFeed.map((item) => ({
        ...item,
        typeLabel: typeLabel(item.type),
        statusLabel: statusLabel(item.status),
        occurredAtLabel: formatDateTime(item.occurredAt, locale.value),
    })),
);
const timeDistributionChart = computed(() => timeTrackingDistributionChart(props.summary, t));
const comparisonChart = computed(() => timeTrackingComparisonChart(props.comparison, t));
const localizedComparisonMetrics = computed(() => localizedTimeTrackingComparisonMetrics(props.comparison?.metrics, t));
const localizedComparisonUserMetrics = computed(() => localizedTimeTrackingComparisonMetrics(props.comparison?.userMetrics, t));

watch(
    () => props.filters,
    () => {
        filters.value = { ...filterDefaults, ...props.filters };
    },
);

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function formatDuration(seconds: number): string {
    return formatTimeTrackingDuration(seconds, t);
}

function statusLabel(status: string): string {
    return timeTrackingStatusLabel(status, t);
}

function typeLabel(type: string): string {
    return timeTrackingTypeLabel(type, t);
}

function contextLabel(context: string): string {
    return timeTrackingContextLabel(context, t);
}

function exceededBreakRowClass(row: TimeReportRow): string {
    return row.rawType === 'break' && (Number(row.excessBreakSeconds ?? 0) > 0 || row.breakLimitStatus === 'exceeded')
        ? 'bg-rose-50/80 dark:bg-rose-950/25 [&>td]:!text-rose-950 dark:[&>td]:!text-rose-100'
        : '';
}
</script>

<template>
    <Head :title="t('pages.time_tracking.manager_report.head_title')" />
    <AppLayout :title="t('pages.time_tracking.manager_report.title')" :title-icon="IconClockHour4" mode="manager">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.scope')"
                    :value="scopeLabel"
                    :icon="IconUsersGroup"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.visible_users')"
                    :value="teamSummary.visibleUsers"
                    :icon="IconUsersGroup"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.working')"
                    :value="teamSummary.working"
                    :icon="IconHourglass"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.break')"
                    :value="teamSummary.break"
                    :icon="IconPlayerPause"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.other_work')"
                    :value="teamSummary.otherWork"
                    :icon="IconRefresh"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.manager_report.metrics.no_session')"
                    :value="teamSummary.noSession"
                    :icon="IconUserMinus"
                    tone="zinc"
                />
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.total')"
                    :value="formatDuration(summary.totalSeconds)"
                    :icon="IconClockHour4"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.work')"
                    :value="formatDuration(summary.workSeconds)"
                    :icon="IconHourglass"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.break')"
                    :value="formatDuration(summary.breakSeconds)"
                    :icon="IconPlayerPause"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.other_work')"
                    :value="formatDuration(summary.otherWorkSeconds)"
                    :icon="IconRefresh"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.corrections')"
                    :value="summary.corrections"
                    :icon="IconFilePencil"
                    tone="zinc"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.pending')"
                    :value="summary.pending"
                    :icon="IconAlertCircle"
                    :tone="summary.pending > 0 ? 'rose' : 'zinc'"
                />
            </div>

            <SurfaceCard
                :title="t('pages.time_tracking.manager_report.feed.title')"
                :subtitle="t('pages.time_tracking.manager_report.feed.subtitle')"
                :icon="IconRefresh"
                tone="sky"
            >
                <UiState
                    v-if="localizedStatusFeed.length === 0"
                    variant="empty"
                    size="compact"
                    :title="t('pages.time_tracking.manager_report.feed.empty_title')"
                    :description="t('pages.time_tracking.manager_report.feed.empty_description')"
                />
                <ol v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <li
                        v-for="item in localizedStatusFeed"
                        :key="item.publicId"
                        class="grid gap-2 py-3 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ item.userName }}</p>
                                <span
                                    class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                                >
                                    {{ item.typeLabel }}
                                </span>
                                <span
                                    class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"
                                >
                                    {{ item.statusLabel }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-zinc-500 dark:text-zinc-400">
                                {{ item.context }}
                            </p>
                        </div>
                        <time class="text-sm text-zinc-500 dark:text-zinc-400" :datetime="item.occurredAt">
                            {{ item.occurredAtLabel }}
                        </time>
                    </li>
                </ol>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.time_tracking.user_report.charts.title')" :icon="IconHourglass" tone="sky">
                    <AtlasBarChart :chart="timeDistributionChart" tone="sky" />
                </SurfaceCard>

                <SurfaceCard :title="t('pages.time_tracking.user_report.comparison.card_title')" :icon="IconRefresh" tone="teal">
                    <AtlasBarChart v-if="comparisonChart" :chart="comparisonChart" tone="teal" />
                    <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ t('pages.time_tracking.user_report.comparison.disabled') }}
                    </p>
                    <dl v-if="localizedComparisonMetrics.length > 0" class="mt-4 grid gap-2 sm:grid-cols-3">
                        <div
                            v-for="metric in localizedComparisonMetrics"
                            :key="metric.metric"
                            class="rounded-md border border-zinc-200 p-3 dark:border-zinc-800"
                        >
                            <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ metric.label }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ metric.delta }}</dd>
                            <dd class="text-xs text-zinc-500 dark:text-zinc-400">{{ metric.percent }}</dd>
                        </div>
                    </dl>
                </SurfaceCard>
            </div>

            <SurfaceCard
                :title="t('pages.time_tracking.manager_report.comparison_users.title')"
                :subtitle="t('pages.time_tracking.manager_report.comparison_users.subtitle')"
                :icon="IconUsersGroup"
                v-if="localizedComparisonUserMetrics.length > 0"
                tone="zinc"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.table.user') }}</th>
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.comparison_users.metric') }}</th>
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.comparison_users.current') }}</th>
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.comparison_users.previous') }}</th>
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.comparison_users.delta') }}</th>
                                <th class="px-3 py-2">{{ t('pages.time_tracking.manager_report.comparison_users.percent') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                            <tr v-for="metric in localizedComparisonUserMetrics" :key="`${metric.userPublicId}-${metric.metric}`">
                                <td class="px-3 py-2 font-medium text-zinc-950 dark:text-zinc-50">{{ metric.userName }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ metric.label }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ metric.current }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ metric.previous }}</td>
                                <td class="px-3 py-2 font-medium text-zinc-950 dark:text-zinc-50">{{ metric.delta }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ metric.percent }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SurfaceCard>

            <FilterPanel
                :title="t('pages.time_tracking.user_report.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect
                        v-model="filters.range"
                        :label="t('pages.time_tracking.user_report.filters.range')"
                        :options="rangeOptions"
                    />
                    <FormDateInput v-model="filters.from" :label="t('pages.time_tracking.user_report.filters.from')" />
                    <FormDateInput v-model="filters.to" :label="t('pages.time_tracking.user_report.filters.to')" />
                    <FormSelect v-model="filters.type" :label="t('pages.time_tracking.user_report.filters.type')" :options="typeOptions" />
                    <FormSelect
                        v-model="filters.status"
                        :label="t('pages.time_tracking.user_report.filters.status')"
                        :options="statusOptions"
                    />
                    <FormSelect
                        v-model="filters.module"
                        :label="t('pages.time_tracking.user_report.filters.module')"
                        :options="moduleOptions"
                    />
                    <FormSelect
                        v-model="filters.compare"
                        :label="t('pages.time_tracking.user_report.filters.compare')"
                        :options="compareOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.time_tracking.manager_report.table.title')"
                :rows="localizedRows"
                :columns="columns"
                row-key="publicId"
                :empty-label="t('pages.time_tracking.manager_report.empty')"
                :total-rows="table.pagination.total"
                :ui-locale="locale"
                :table="table"
                :filters="filters"
                :row-class="exceededBreakRowClass"
            />
        </PageStack>
    </AppLayout>
</template>
