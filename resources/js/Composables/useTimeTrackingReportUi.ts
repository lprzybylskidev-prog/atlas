import type { FormSelectOption } from '../Components/Form/FormSelect.vue';
import type { AtlasBarChartData } from '../Types/charts';
import { formatStatus } from '../Utils/formatters';
import { moduleLabel } from '../Utils/moduleLabels';

interface TimeTrackingSummary {
    totalSeconds: number;
    workSeconds: number;
    breakSeconds: number;
    technicalBreakSeconds?: number;
    maintenanceSeconds?: number;
    otherWorkSeconds: number;
    acceptedOtherWorkSeconds?: number;
    pendingOtherWorkSeconds?: number;
}

interface ComparisonMetric {
    metric: string;
    currentSeconds: number;
    previousSeconds: number;
    deltaSeconds: number;
    percentDelta: number | null;
}

interface TimeTrackingComparison {
    rangeLabel: string;
    previousRangeLabel: string;
    metrics: ComparisonMetric[];
}

type Translator = (key: string, replacements?: Record<string, string | number>) => string;

export function timeTrackingRangeOptions(t: Translator): FormSelectOption[] {
    return [
        { value: 'today', label: t('pages.time_tracking.user_report.filters.today') },
        { value: 'week', label: t('pages.time_tracking.user_report.filters.week') },
        { value: 'settlement_period', label: t('pages.time_tracking.user_report.filters.settlement_period') },
        { value: 'month', label: t('pages.time_tracking.user_report.filters.month') },
        { value: 'year', label: t('pages.time_tracking.user_report.filters.year') },
        { value: 'all', label: t('pages.time_tracking.user_report.filters.all') },
        { value: 'custom', label: t('pages.time_tracking.user_report.filters.custom') },
    ];
}

export function timeTrackingTypeOptions(t: Translator): FormSelectOption[] {
    return [
        { value: 'all', label: t('pages.time_tracking.user_report.filters.any_type') },
        { value: 'work', label: t('pages.time_tracking.user_report.types.work') },
        { value: 'break', label: t('pages.time_tracking.user_report.types.break') },
        { value: 'other_work', label: t('pages.time_tracking.user_report.types.other_work') },
        { value: 'correction', label: t('pages.time_tracking.user_report.types.correction') },
    ];
}

export function timeTrackingStatusOptions(t: Translator): FormSelectOption[] {
    return [
        { value: 'all', label: t('pages.time_tracking.user_report.filters.any_status') },
        { value: 'open', label: t('pages.time_tracking.user_report.status.open') },
        { value: 'closed', label: t('pages.time_tracking.user_report.status.closed') },
        { value: 'pending', label: t('datatable.status.pending') },
        { value: 'corrected', label: t('pages.time_tracking.user_report.status.corrected') },
        { value: 'rejected', label: t('pages.time_tracking.user_report.status.rejected') },
        { value: 'cancelled', label: t('pages.time_tracking.user_report.status.cancelled') },
        { value: 'under_review', label: t('pages.time_tracking.user_report.status.under_review') },
        { value: 'approved', label: t('pages.time_tracking.user_report.status.approved') },
    ];
}

export function timeTrackingCompareOptions(t: Translator): FormSelectOption[] {
    return [
        { value: 'off', label: t('pages.time_tracking.user_report.filters.compare_off') },
        { value: 'previous', label: t('pages.time_tracking.user_report.filters.compare_previous') },
    ];
}

export function formatTimeTrackingDuration(seconds: number, t: Translator): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return t('pages.time_tracking.user_report.duration', { hours, minutes });
}

export function formatSignedTimeTrackingDuration(seconds: number, t: Translator): string {
    const prefix = seconds > 0 ? '+' : seconds < 0 ? '-' : '';

    return `${prefix}${formatTimeTrackingDuration(Math.abs(seconds), t)}`;
}

export function timeTrackingMetricLabel(metric: string, t: Translator): string {
    return t(`pages.time_tracking.user_report.types.${metric}`);
}

export function timeTrackingStatusLabel(status: string, t: Translator): string {
    const key = `pages.time_tracking.user_report.status.${status}`;
    const translated = t(key);

    return translated === key ? formatStatus(status) : translated;
}

export function timeTrackingTypeLabel(type: string, t: Translator): string {
    const key = `pages.time_tracking.user_report.types.${type}`;
    const translated = t(key);

    return translated === key ? formatStatus(type) : translated;
}

export function timeTrackingContextLabel(context: string, t: Translator): string {
    if (context === 'System' || context === 'system') {
        return t('pages.time_tracking.user_report.context.system');
    }

    if (context === 'Workspace' || context === 'workspace') {
        return t('pages.time_tracking.user_report.context.workspace');
    }

    const routeContext = context.includes('.') ? (context.split('.')[1] ?? context) : context;

    return moduleLabel(routeContext, t);
}

export function secondsToReportHours(seconds: number): number {
    return Math.round((seconds / 3600) * 100) / 100;
}

export function timeTrackingDistributionChart(summary: TimeTrackingSummary, t: Translator): AtlasBarChartData {
    return {
        title: t('pages.time_tracking.user_report.charts.distribution_title'),
        description: t('pages.time_tracking.user_report.charts.distribution_description'),
        unit: t('pages.time_tracking.user_report.charts.hours_unit'),
        series: [
            {
                label: t('pages.time_tracking.user_report.charts.current_period'),
                points: [
                    { label: t('pages.time_tracking.user_report.daily_table.counted'), value: secondsToReportHours(summary.totalSeconds) },
                    { label: t('pages.time_tracking.user_report.types.work'), value: secondsToReportHours(summary.workSeconds) },
                    { label: t('pages.time_tracking.user_report.types.break'), value: secondsToReportHours(summary.breakSeconds) },
                    {
                        label: t('pages.time_tracking.user_report.daily_table.technical_break'),
                        value: secondsToReportHours(summary.technicalBreakSeconds ?? 0),
                    },
                    {
                        label: t('pages.time_tracking.user_report.daily_table.maintenance'),
                        value: secondsToReportHours(summary.maintenanceSeconds ?? 0),
                    },
                    { label: t('pages.time_tracking.user_report.types.other_work'), value: secondsToReportHours(summary.otherWorkSeconds) },
                    {
                        label: t('pages.time_tracking.user_report.daily_table.accepted_other_work'),
                        value: secondsToReportHours(summary.acceptedOtherWorkSeconds ?? 0),
                    },
                    {
                        label: t('pages.time_tracking.user_report.daily_table.pending_other_work'),
                        value: secondsToReportHours(summary.pendingOtherWorkSeconds ?? 0),
                    },
                ],
            },
        ],
    };
}

export function timeTrackingComparisonChart(comparison: TimeTrackingComparison | null, t: Translator): AtlasBarChartData | null {
    if (comparison === null) {
        return null;
    }

    return {
        title: t('pages.time_tracking.user_report.comparison.title'),
        description: t('pages.time_tracking.user_report.comparison.description', {
            current: comparison.rangeLabel,
            previous: comparison.previousRangeLabel,
        }),
        unit: t('pages.time_tracking.user_report.charts.hours_unit'),
        series: [
            {
                label: t('pages.time_tracking.user_report.comparison.current'),
                points: comparison.metrics.map((metric) => ({
                    label: timeTrackingMetricLabel(metric.metric, t),
                    value: secondsToReportHours(metric.currentSeconds),
                })),
            },
            {
                label: t('pages.time_tracking.user_report.comparison.previous'),
                points: comparison.metrics.map((metric) => ({
                    label: timeTrackingMetricLabel(metric.metric, t),
                    value: secondsToReportHours(metric.previousSeconds),
                })),
            },
        ],
    };
}

export function localizedTimeTrackingComparisonMetrics<TMetric extends ComparisonMetric>(metrics: TMetric[] | undefined, t: Translator) {
    return (
        metrics?.map((metric) => ({
            ...metric,
            label: timeTrackingMetricLabel(metric.metric, t),
            current: formatTimeTrackingDuration(metric.currentSeconds, t),
            previous: formatTimeTrackingDuration(metric.previousSeconds, t),
            delta: formatSignedTimeTrackingDuration(metric.deltaSeconds, t),
            percent: metric.percentDelta === null ? t('pages.time_tracking.user_report.comparison.no_previous') : `${metric.percentDelta}%`,
        })) ?? []
    );
}
