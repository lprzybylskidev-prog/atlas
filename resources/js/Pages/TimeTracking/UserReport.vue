<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconBriefcase,
    IconClockHour4,
    IconDatabase,
    IconFilePencil,
    IconHourglass,
    IconPlayerPause,
    IconRefresh,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import AtlasBarChart from '../../Components/AtlasBarChart.vue';
import DataTable from '../../Components/DataTable.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import PageStack from '../../Components/PageStack.vue';
import SectionHeader from '../../Components/SectionHeader.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import TimeTrackingCorrectionDialog, {
    type TimeTrackingCorrectionSource,
} from '../../Components/TimeTracking/TimeTrackingCorrectionDialog.vue';
import TimeTrackingReportMetrics from '../../Components/TimeTracking/TimeTrackingReportMetrics.vue';
import TimeTrackingReportTabs, { type TimeTrackingReportSection } from '../../Components/TimeTracking/TimeTrackingReportTabs.vue';
import {
    formatTimeTrackingDuration,
    localizedTimeTrackingComparisonMetrics,
    timeTrackingCompareOptions,
    timeTrackingComparisonChart,
    timeTrackingDistributionChart,
    timeTrackingRangeOptions,
    timeTrackingStatusLabel,
} from '../../Composables/useTimeTrackingReportUi';
import { applyTableFilters, clearTableFilters } from '../../Composables/useTableFilterControls';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../Types/data-table';
import type {
    BreakRow,
    CorrectionRow,
    DailyWorkTimeRow,
    LocalizedBreakRow,
    LocalizedCorrectionRow,
    LocalizedDailyWorkTimeRow,
    LocalizedOtherWorkRow,
    LocalizedSourceTimeRow,
    OtherWorkRow,
    SourceTimeRow,
    TimeReportComparison,
    TimeReportSummary,
} from '../../Types/time-tracking';

const props = defineProps<{
    section: TimeTrackingReportSection;
    dailyRows: DailyWorkTimeRow[];
    workSessionRows: SourceTimeRow[];
    breakRows: BreakRow[];
    otherWorkRows: OtherWorkRow[];
    correctionRows: CorrectionRow[];
    summary: TimeReportSummary;
    comparison: TimeReportComparison | null;
    filters: Record<string, string>;
    dailyTable: DataTableMeta;
    workSessionsTable: DataTableMeta;
    breaksTable: DataTableMeta;
    otherWorkTable: DataTableMeta;
    correctionsTable: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const activeView = computed(() => props.section);
const correctionDialogOpen = ref(false);
const selectedCorrectionSource = ref<TimeTrackingCorrectionSource | null>(null);
const filterKeys = ['range', 'from', 'to', 'status', 'compare'];
const filterDefaults = {
    range: 'settlement_period',
    from: '',
    to: '',
    status: 'all',
    compare: 'off',
};
const filters = ref({ ...filterDefaults, ...props.filters });
const appliedFilters = computed(() => ({ ...filterDefaults, ...props.filters }));

const dailyColumns = computed<DataTableColumn<LocalizedDailyWorkTimeRow>[]>(() => [
    { key: 'date', label: t('pages.time_tracking.user_report.daily_table.date') },
    { key: 'countedDuration', label: t('pages.time_tracking.user_report.daily_table.counted') },
    { key: 'workDuration', label: t('pages.time_tracking.user_report.daily_table.work') },
    { key: 'breakDuration', label: t('pages.time_tracking.user_report.daily_table.break') },
    { key: 'technicalBreakDuration', label: t('pages.time_tracking.user_report.daily_table.technical_break') },
    { key: 'maintenanceDuration', label: t('pages.time_tracking.user_report.daily_table.maintenance') },
    { key: 'otherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.other_work') },
    { key: 'acceptedOtherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.accepted_other_work') },
    { key: 'pendingOtherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.pending_other_work') },
    { key: 'localizedSessionStatus', label: t('pages.time_tracking.user_report.daily_table.status'), format: 'status-badge' },
]);
const workSessionColumns = computed<DataTableColumn<LocalizedSourceTimeRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.work_sessions_table.public_id'), access: 'forbidden' },
    { key: 'sourceType', label: t('pages.time_tracking.user_report.table.source_type'), hidden: true },
    { key: 'statusLabel', label: t('pages.time_tracking.user_report.work_sessions_table.status'), format: 'status-badge' },
    { key: 'startedAt', label: t('pages.time_tracking.user_report.work_sessions_table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.user_report.work_sessions_table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.user_report.work_sessions_table.duration') },
    { key: 'exactSeconds', label: t('pages.time_tracking.user_report.table.exact_seconds'), access: 'forbidden' },
]);
const breakColumns = computed<DataTableColumn<LocalizedBreakRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.breaks_table.public_id'), access: 'forbidden' },
    { key: 'sourceType', label: t('pages.time_tracking.user_report.table.source_type'), hidden: true },
    { key: 'statusLabel', label: t('pages.time_tracking.user_report.breaks_table.status'), format: 'status-badge' },
    { key: 'startedAt', label: t('pages.time_tracking.user_report.breaks_table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.user_report.breaks_table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.user_report.breaks_table.duration') },
    { key: 'breakLimitLabel', label: t('pages.time_tracking.user_report.breaks_table.limit_status'), format: 'status-badge' },
    { key: 'excessBreakDuration', label: t('pages.time_tracking.user_report.breaks_table.excess') },
    { key: 'requiresManagerReview', label: t('pages.time_tracking.user_report.breaks_table.requires_review'), format: 'boolean' },
    { key: 'exactSeconds', label: t('pages.time_tracking.user_report.table.exact_seconds'), access: 'forbidden' },
]);
const otherWorkColumns = computed<DataTableColumn<LocalizedOtherWorkRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.other_work_table.public_id'), access: 'forbidden' },
    { key: 'sourceType', label: t('pages.time_tracking.user_report.table.source_type'), hidden: true },
    { key: 'category', label: t('pages.time_tracking.user_report.other_work_table.category') },
    { key: 'description', label: t('pages.time_tracking.user_report.other_work_table.description') },
    { key: 'endNote', label: t('pages.time_tracking.user_report.other_work_table.end_note'), hidden: true },
    { key: 'status', label: t('pages.time_tracking.user_report.other_work_table.status'), format: 'status-badge' },
    { key: 'requiresManagerDecision', label: t('pages.time_tracking.user_report.other_work_table.decision_state'), format: 'boolean' },
    { key: 'startedAt', label: t('pages.time_tracking.user_report.other_work_table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.user_report.other_work_table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.user_report.other_work_table.duration') },
    { key: 'closureReason', label: t('pages.time_tracking.user_report.other_work_table.closure_reason'), hidden: true },
]);
const correctionColumns = computed<DataTableColumn<LocalizedCorrectionRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.corrections_table.public_id'), access: 'forbidden' },
    { key: 'sourceType', label: t('pages.time_tracking.user_report.corrections_table.source_type'), format: 'status-badge' },
    { key: 'typeLabel', label: t('pages.time_tracking.user_report.corrections_table.type') },
    { key: 'statusLabel', label: t('pages.time_tracking.user_report.corrections_table.status'), format: 'status-badge' },
    { key: 'description', label: t('pages.time_tracking.user_report.corrections_table.description') },
    { key: 'requestedAt', label: t('pages.time_tracking.user_report.corrections_table.requested_at'), format: 'datetime' },
    { key: 'decidedAt', label: t('pages.time_tracking.user_report.corrections_table.decided_at'), format: 'datetime' },
    { key: 'decisionReason', label: t('pages.time_tracking.user_report.corrections_table.decision_reason'), hidden: true },
]);
const rangeOptions = computed<FormSelectOption[]>(() => timeTrackingRangeOptions(t));
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.time_tracking.user_report.filters.any_status') },
    { value: 'pending', label: t('pages.time_tracking.user_report.status.pending') },
    { value: 'under_review', label: t('pages.time_tracking.user_report.status.under_review') },
    { value: 'approved', label: t('pages.time_tracking.user_report.status.approved') },
    { value: 'rejected', label: t('pages.time_tracking.user_report.status.rejected') },
    { value: 'cancelled', label: t('pages.time_tracking.user_report.status.cancelled') },
]);
const compareOptions = computed<FormSelectOption[]>(() => timeTrackingCompareOptions(t));
const localizedDailyRows = computed<LocalizedDailyWorkTimeRow[]>(() =>
    props.dailyRows.map((row) => ({
        ...row,
        countedDuration: formatDuration(row.countedSeconds),
        workDuration: formatDuration(row.workSeconds),
        breakDuration: formatDuration(row.breakSeconds),
        technicalBreakDuration: formatDuration(row.technicalBreakSeconds),
        maintenanceDuration: formatDuration(row.maintenanceSeconds),
        otherWorkDuration: formatDuration(row.otherWorkSeconds),
        acceptedOtherWorkDuration: formatDuration(row.acceptedOtherWorkSeconds),
        pendingOtherWorkDuration: formatDuration(row.pendingOtherWorkSeconds),
        localizedSessionStatus: row.sessionStatus,
    })),
);
const localizedOtherWorkRows = computed<LocalizedOtherWorkRow[]>(() =>
    props.otherWorkRows.map((row) => ({
        ...row,
        category: localizedOtherWorkCategory(row),
        duration: formatDuration(row.exactSeconds),
        status: row.status,
        decisionState: decisionStateLabel(row.decisionState),
        closureReason: row.closureReason === '' ? '' : statusLabel(row.closureReason),
    })),
);
const localizedWorkSessionRows = computed<LocalizedSourceTimeRow[]>(() => localizedSourceRows(props.workSessionRows));
const localizedBreakRows = computed<LocalizedBreakRow[]>(() =>
    props.breakRows.map((row) => ({
        ...row,
        statusLabel: statusLabel(row.status),
        breakLimitLabel: row.breakLimitStatus,
        excessBreakDuration: formatDuration(row.excessBreakSeconds),
        duration: formatDuration(row.exactSeconds),
    })),
);
const localizedCorrectionRows = computed<LocalizedCorrectionRow[]>(() =>
    props.correctionRows.map((row) => ({
        ...row,
        sourceType: row.sourceType === '' ? 'none' : row.sourceType,
        typeLabel: correctionTypeLabel(row.type),
        statusLabel: statusLabel(row.status),
    })),
);
const correctionActions = computed<DataTableAction<LocalizedSourceTimeRow | LocalizedBreakRow | LocalizedOtherWorkRow>[]>(() => [
    {
        key: 'request_correction',
        label: t('pages.time_tracking.user_report.actions.request_correction'),
        tone: 'warning',
        visible: (row) => row.availableActions.includes('request_correction'),
        onAction: (row) => openCorrectionDialog(row),
    },
]);
const timeDistributionChart = computed(() => timeTrackingDistributionChart(props.summary, t));
const comparisonChart = computed(() => timeTrackingComparisonChart(props.comparison, t));
const localizedComparisonMetrics = computed(() => localizedTimeTrackingComparisonMetrics(props.comparison?.metrics, t));
const activeTableTitle = computed(() => t(`pages.time_tracking.user_report.tabs.${activeView.value}`));
const activeTableIcon = computed(() => {
    if (activeView.value === 'work_sessions') {
        return IconDatabase;
    }

    if (activeView.value === 'breaks') {
        return IconPlayerPause;
    }

    if (activeView.value === 'other_work') {
        return IconBriefcase;
    }

    if (activeView.value === 'corrections') {
        return IconFilePencil;
    }

    return IconClockHour4;
});

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

function decisionStateLabel(state: string): string {
    const key = `pages.time_tracking.user_report.decision_state.${state}`;
    const label = t(key);

    return label === key ? state : label;
}

function localizedOtherWorkCategory(row: OtherWorkRow): string {
    const label = locale.value === 'pl' ? row.categoryLabelPl : row.categoryLabelEn;

    if (label !== '') {
        return label;
    }

    return t('pages.time_tracking.other_work_lock.category.none');
}

function localizedSourceRows(rows: SourceTimeRow[]): LocalizedSourceTimeRow[] {
    return rows.map((row) => ({
        ...row,
        statusLabel: statusLabel(row.status),
        duration: formatDuration(row.exactSeconds),
    }));
}

function sourceTypeLabel(sourceType: string): string {
    const key = `pages.time_tracking.user_report.sources.${sourceType === '' ? 'none' : sourceType}`;
    const label = t(key);

    return label === key ? sourceType : label;
}

function correctionTypeLabel(type: string): string {
    const key = `pages.time_tracking.user_report.correction_types.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

function exceededBreakRowClass(row: LocalizedBreakRow): string {
    return row.excessBreakSeconds > 0 || row.breakLimitStatus === 'exceeded'
        ? 'bg-rose-50/80 dark:bg-rose-950/25 [&>td]:!text-rose-950 dark:[&>td]:!text-rose-100'
        : '';
}

function sourceSubject(row: SourceTimeRow | BreakRow | OtherWorkRow): string {
    const startedAt = String(row.startedAt ?? '');
    const label = sourceTypeLabel(row.sourceType);

    return startedAt === '' ? label : `${label} - ${startedAt}`;
}

function openCorrectionDialog(row: SourceTimeRow | BreakRow | OtherWorkRow): void {
    selectedCorrectionSource.value = {
        sourceType: row.sourceType,
        sourcePublicId: row.publicId,
        subject: sourceSubject(row),
    };
    correctionDialogOpen.value = true;
}
</script>

<template>
    <Head :title="t('pages.time_tracking.user_report.head_title')" />
    <AppLayout :title="t('pages.time_tracking.user_report.title')" :title-icon="IconClockHour4" mode="user">
        <PageStack>
            <TimeTrackingReportMetrics :section="section" :summary="summary" />

            <FilterPanel
                :title="t('pages.time_tracking.user_report.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect
                        v-model="filters.range"
                        :label="t('pages.time_tracking.user_report.filters.range')"
                        :options="rangeOptions"
                    />
                    <FormDateInput v-model="filters.from" :label="t('pages.time_tracking.user_report.filters.from')" />
                    <FormDateInput v-model="filters.to" :label="t('pages.time_tracking.user_report.filters.to')" />
                    <FormSelect
                        v-model="filters.status"
                        :label="t('pages.time_tracking.user_report.filters.status')"
                        :options="statusOptions"
                    />
                    <FormSelect
                        v-model="filters.compare"
                        :label="t('pages.time_tracking.user_report.filters.compare')"
                        :options="compareOptions"
                    />
                </div>
            </FilterPanel>

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

            <SectionHeader :title="activeTableTitle" :icon="activeTableIcon">
                <template #actions>
                    <TimeTrackingReportTabs :active="activeView" />
                </template>
            </SectionHeader>

            <DataTable
                v-if="activeView === 'daily'"
                :title="t('pages.time_tracking.user_report.daily_table.title')"
                :rows="localizedDailyRows"
                :columns="dailyColumns"
                row-key="date"
                :empty-label="t('pages.time_tracking.user_report.daily_table.empty')"
                :total-rows="dailyTable.pagination.total"
                :ui-locale="locale"
                :table="dailyTable"
                :filters="appliedFilters"
            />
            <DataTable
                v-else-if="activeView === 'work_sessions'"
                :title="t('pages.time_tracking.user_report.work_sessions_table.title')"
                :rows="localizedWorkSessionRows"
                :columns="workSessionColumns"
                :actions="correctionActions"
                row-key="publicId"
                :empty-label="t('pages.time_tracking.user_report.work_sessions_table.empty')"
                :ui-locale="locale"
                :table="workSessionsTable"
                :filters="appliedFilters"
            />
            <DataTable
                v-else-if="activeView === 'breaks'"
                :title="t('pages.time_tracking.user_report.breaks_table.title')"
                :rows="localizedBreakRows"
                :columns="breakColumns"
                :actions="correctionActions"
                row-key="publicId"
                :empty-label="t('pages.time_tracking.user_report.breaks_table.empty')"
                :ui-locale="locale"
                :table="breaksTable"
                :filters="appliedFilters"
                :row-class="exceededBreakRowClass"
            />
            <DataTable
                v-else-if="activeView === 'other_work'"
                :title="t('pages.time_tracking.user_report.other_work_table.title')"
                :rows="localizedOtherWorkRows"
                :columns="otherWorkColumns"
                :actions="correctionActions"
                row-key="publicId"
                :empty-label="t('pages.time_tracking.user_report.other_work_table.empty')"
                :ui-locale="locale"
                :table="otherWorkTable"
                :filters="appliedFilters"
            />
            <DataTable
                v-else
                :title="t('pages.time_tracking.user_report.corrections_table.title')"
                :rows="localizedCorrectionRows"
                :columns="correctionColumns"
                row-key="publicId"
                :empty-label="t('pages.time_tracking.user_report.corrections_table.empty')"
                :ui-locale="locale"
                :table="correctionsTable"
                :filters="appliedFilters"
            />

            <TimeTrackingCorrectionDialog v-model:open="correctionDialogOpen" :source="selectedCorrectionSource" />
        </PageStack>
    </AppLayout>
</template>
