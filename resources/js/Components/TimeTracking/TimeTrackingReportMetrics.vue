<script setup lang="ts">
import {
    IconAlertCircle,
    IconBriefcase,
    IconCheck,
    IconClockHour4,
    IconDatabase,
    IconFilePencil,
    IconHourglass,
    IconPlayerPause,
    IconRefresh,
    IconUsers,
    IconX,
} from '@tabler/icons-vue';
import { computed } from 'vue';
import type { Component } from 'vue';

import { formatTimeTrackingDuration } from '../../Composables/useTimeTrackingReportUi';
import { useTranslator } from '../../Localization/translator';
import type { TimeReportSummary } from '../../Types/time-tracking';
import OperationalMetricTile from '../OperationalMetricTile.vue';
import type { TimeTrackingReportSection } from './TimeTrackingReportTabs.vue';

type MetricTone = 'teal' | 'sky' | 'amber' | 'emerald' | 'rose' | 'zinc';

interface Metric {
    label: string;
    value: string | number;
    icon: Component;
    tone: MetricTone;
}

const props = withDefaults(
    defineProps<{
        section: TimeTrackingReportSection;
        summary: TimeReportSummary;
        multiUser?: boolean;
    }>(),
    { multiUser: false },
);

const { t } = useTranslator();
const duration = (seconds: number): string => formatTimeTrackingDuration(seconds, t);
const usersMetric = computed<Metric>(() => ({
    label: t('pages.time_tracking.user_report.metrics.users'),
    value: props.summary.users,
    icon: IconUsers,
    tone: 'zinc',
}));
const lastAudienceMetric = computed<Metric>(() =>
    props.multiUser
        ? usersMetric.value
        : {
              label: t('pages.time_tracking.user_report.metrics.closed'),
              value: props.summary.closed,
              icon: IconCheck,
              tone: 'zinc',
          },
);

const metrics = computed<Metric[]>(() => {
    if (props.section === 'other_work') {
        return [
            {
                label: t('pages.time_tracking.user_report.metrics.other_work'),
                value: duration(props.summary.otherWorkSeconds),
                icon: IconBriefcase,
                tone: 'emerald',
            },
            { label: t('pages.time_tracking.user_report.metrics.records'), value: props.summary.records, icon: IconDatabase, tone: 'sky' },
            {
                label: t('pages.time_tracking.user_report.metrics.accepted_other_work'),
                value: duration(props.summary.acceptedOtherWorkSeconds),
                icon: IconCheck,
                tone: 'teal',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.pending_other_work'),
                value: duration(props.summary.pendingOtherWorkSeconds),
                icon: IconHourglass,
                tone: 'amber',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.requires_review'),
                value: props.summary.requiresReview,
                icon: IconAlertCircle,
                tone: props.summary.requiresReview > 0 ? 'rose' : 'zinc',
            },
            props.multiUser
                ? lastAudienceMetric.value
                : {
                      label: t('pages.time_tracking.user_report.metrics.approved'),
                      value: props.summary.approved,
                      icon: IconCheck,
                      tone: 'zinc',
                  },
        ];
    }

    if (props.section === 'breaks') {
        return [
            {
                label: t('pages.time_tracking.user_report.metrics.break'),
                value: duration(props.summary.breakSeconds),
                icon: IconPlayerPause,
                tone: 'amber',
            },
            { label: t('pages.time_tracking.user_report.metrics.records'), value: props.summary.records, icon: IconDatabase, tone: 'sky' },
            {
                label: t('pages.time_tracking.user_report.metrics.excess'),
                value: duration(props.summary.excessSeconds),
                icon: IconAlertCircle,
                tone: props.summary.excessSeconds > 0 ? 'rose' : 'zinc',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.requires_review'),
                value: props.summary.requiresReview,
                icon: IconRefresh,
                tone: props.summary.requiresReview > 0 ? 'rose' : 'zinc',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.open'),
                value: props.summary.open,
                icon: IconClockHour4,
                tone: props.summary.open > 0 ? 'amber' : 'zinc',
            },
            props.multiUser
                ? lastAudienceMetric.value
                : {
                      label: t('pages.time_tracking.user_report.metrics.manual_entries'),
                      value: props.summary.manualEntries,
                      icon: IconRefresh,
                      tone: 'zinc',
                  },
        ];
    }

    if (props.section === 'corrections') {
        return [
            {
                label: t('pages.time_tracking.user_report.metrics.corrections'),
                value: props.summary.corrections,
                icon: IconFilePencil,
                tone: 'sky',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.pending'),
                value: props.summary.pending,
                icon: IconHourglass,
                tone: props.summary.pending > 0 ? 'rose' : 'zinc',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.corrected'),
                value: props.summary.corrected,
                icon: IconCheck,
                tone: 'teal',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.rejected'),
                value: props.summary.rejected,
                icon: IconX,
                tone: props.summary.rejected > 0 ? 'rose' : 'zinc',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.manual_entries'),
                value: props.summary.manualEntries,
                icon: IconRefresh,
                tone: 'zinc',
            },
            props.multiUser
                ? usersMetric.value
                : {
                      label: t('pages.time_tracking.user_report.metrics.requires_review'),
                      value: props.summary.requiresReview,
                      icon: IconAlertCircle,
                      tone: props.summary.requiresReview > 0 ? 'rose' : 'zinc',
                  },
        ];
    }

    if (props.section === 'work_sessions') {
        return [
            {
                label: t('pages.time_tracking.user_report.metrics.work'),
                value: duration(props.summary.workSeconds),
                icon: IconHourglass,
                tone: 'sky',
            },
            {
                label: t('pages.time_tracking.admin_operations.metrics.work_sessions'),
                value: props.summary.records,
                icon: IconDatabase,
                tone: 'teal',
            },
            {
                label: t('pages.time_tracking.user_report.metrics.open'),
                value: props.summary.open,
                icon: IconClockHour4,
                tone: props.summary.open > 0 ? 'amber' : 'zinc',
            },
            { label: t('pages.time_tracking.user_report.metrics.closed'), value: props.summary.closed, icon: IconCheck, tone: 'zinc' },
            {
                label: t('pages.time_tracking.user_report.metrics.related_corrections'),
                value: props.summary.relatedCorrections,
                icon: IconFilePencil,
                tone: 'zinc',
            },
            props.multiUser
                ? usersMetric.value
                : {
                      label: t('pages.time_tracking.user_report.metrics.manual_entries'),
                      value: props.summary.manualEntries,
                      icon: IconRefresh,
                      tone: 'zinc',
                  },
        ];
    }

    return [
        {
            label: t('pages.time_tracking.user_report.metrics.total'),
            value: duration(props.summary.totalSeconds),
            icon: IconClockHour4,
            tone: 'teal',
        },
        {
            label: t('pages.time_tracking.user_report.metrics.work'),
            value: duration(props.summary.workSeconds),
            icon: IconHourglass,
            tone: 'sky',
        },
        {
            label: t('pages.time_tracking.user_report.metrics.break'),
            value: duration(props.summary.breakSeconds),
            icon: IconPlayerPause,
            tone: 'amber',
        },
        {
            label: t('pages.time_tracking.user_report.metrics.other_work'),
            value: duration(props.summary.otherWorkSeconds),
            icon: IconBriefcase,
            tone: 'emerald',
        },
        { label: t('pages.time_tracking.user_report.metrics.records'), value: props.summary.records, icon: IconDatabase, tone: 'zinc' },
        props.multiUser
            ? lastAudienceMetric.value
            : {
                  label: t('pages.time_tracking.user_report.metrics.pending_other_work'),
                  value: duration(props.summary.pendingOtherWorkSeconds),
                  icon: IconRefresh,
                  tone: props.summary.pendingOtherWorkSeconds > 0 ? 'amber' : 'zinc',
              },
    ];
});
</script>

<template>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <OperationalMetricTile
            v-for="metric in metrics"
            :key="metric.label"
            :label="metric.label"
            :value="metric.value"
            :icon="metric.icon"
            :tone="metric.tone"
        />
    </div>
</template>
