<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconActivityHeartbeat, IconAlertTriangle, IconPlugConnected, IconRotateClockwise } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed } from 'vue';

import SurfaceCard from '../../../Components/SurfaceCard.vue';
import DataTable from '../../../Components/DataTable.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';

interface IntegrationRecord extends Record<string, unknown> {
    key: string;
    name: string;
    adapterClass: string;
    sourceOfTruth: string;
    providedScopes: string[];
    requiredModules: string[];
    optionalModules: string[];
    enabled: boolean;
    externalApiEnabled: boolean;
    lastSuccessAt: string | null;
    lastErrorAt: string | null;
    lastErrorMessage: string | null;
    circuitState: string | null;
    lastRunStatus: string | null;
    lastRunAt: string | null;
}

interface IntegrationSummary {
    registered: number;
    openCircuits: number;
    running: number;
    failedLastRuns: number;
}

interface RecentRunInput {
    integrationKey: string | null;
    operation: string | null;
    correlationId: string | null;
    status: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    message: string | null;
}

interface RecentRun extends RecentRunInput, Record<string, unknown> {
    rowKey: string;
}

const props = defineProps<{
    integrations: IntegrationRecord[];
    summary: IntegrationSummary;
    externalApiEnabled: boolean;
    recentRuns: RecentRunInput[];
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator();

const summaryItems = computed<{ label: string; value: string; icon: Component; tone: 'amber' | 'emerald' | 'rose' | 'teal' }[]>(() => [
    {
        label: t('pages.admin.integrations.metric.registered'),
        value: String(props.summary.registered),
        icon: IconPlugConnected,
        tone: 'teal',
    },
    { label: t('pages.admin.integrations.metric.running'), value: String(props.summary.running), icon: IconRotateClockwise, tone: 'amber' },
    {
        label: t('pages.admin.integrations.metric.open_circuits'),
        value: String(props.summary.openCircuits),
        icon: IconAlertTriangle,
        tone: props.summary.openCircuits > 0 ? 'rose' : 'emerald',
    },
    {
        label: t('pages.admin.integrations.metric.failed_24h'),
        value: String(props.summary.failedLastRuns),
        icon: IconActivityHeartbeat,
        tone: props.summary.failedLastRuns > 0 ? 'rose' : 'emerald',
    },
]);

const integrationColumns: DataTableColumn<IntegrationRecord>[] = [
    { key: 'name', label: t('pages.admin.integrations.integration') },
    { key: 'key', label: t('pages.admin.integrations.key') },
    { key: 'sourceOfTruth', label: t('pages.admin.integrations.source_of_truth') },
    { key: 'adapterClass', label: t('pages.admin.integrations.adapter'), hidden: true },
    { key: 'circuitState', label: t('pages.admin.integrations.circuit'), format: 'severity' },
    { key: 'lastSuccessAt', label: t('pages.admin.integrations.last_success'), format: 'datetime' },
    { key: 'lastErrorAt', label: t('pages.admin.integrations.last_error'), format: 'datetime' },
    { key: 'lastErrorMessage', label: t('pages.admin.integrations.last_error_message'), hidden: true },
    { key: 'externalApiEnabled', label: t('pages.admin.integrations.external_api'), format: 'boolean', hidden: true },
];

const integrationActions: DataTableAction<IntegrationRecord>[] = [
    {
        key: 'test',
        label: t('pages.admin.integrations.test_connection'),
        method: 'post',
        href: (integration) => `/admin/integrations/${integration.key}/test`,
        tone: 'info',
    },
];

const integrationRows = computed<IntegrationRecord[]>(() =>
    props.integrations.map((integration) => ({
        ...integration,
        circuitState: integration.circuitState ?? 'closed',
    })),
);

const recentRunRows = computed<RecentRun[]>(() =>
    props.recentRuns.map((run, index) => ({
        ...run,
        rowKey: `${run.integrationKey ?? 'integration'}-${run.correlationId ?? index}`,
    })),
);

const recentRunColumns: DataTableColumn<RecentRun>[] = [
    { key: 'integrationKey', label: t('pages.admin.integrations.integration') },
    { key: 'operation', label: t('pages.admin.integrations.operation') },
    { key: 'status', label: t('pages.admin.integrations.status'), format: 'severity' },
    { key: 'startedAt', label: t('pages.admin.integrations.started'), format: 'datetime' },
    { key: 'finishedAt', label: t('pages.admin.integrations.finished'), format: 'datetime', hidden: true },
    { key: 'correlationId', label: t('pages.admin.integrations.correlation') },
    { key: 'message', label: t('pages.admin.integrations.message'), hidden: true },
];
</script>

<template>
    <Head :title="t('pages.admin.integrations.head_title')" />
    <AdminLayout :title="t('pages.admin.integrations.title')" :title-icon="IconPlugConnected">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard
                :title="t('pages.admin.integrations.external_api_boundary')"
                :icon="IconPlugConnected"
                :subtitle="
                    t('pages.admin.integrations.global_access', {
                        state: externalApiEnabled ? t('pages.admin.integrations.enabled') : t('pages.admin.integrations.disabled'),
                    })
                "
                :tone="externalApiEnabled ? 'amber' : 'emerald'"
            >
                <template #actions>
                    <SeverityBadge
                        :value="externalApiEnabled ? 'warning' : 'success'"
                        :label="externalApiEnabled ? t('pages.admin.integrations.enabled') : t('pages.admin.integrations.disabled')"
                    />
                </template>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.integrations.adapters')"
                :rows="integrationRows"
                :columns="integrationColumns"
                row-key="key"
                :actions="integrationActions"
                state-key="admin.integrations.adapters"
                export-key="admin.integrations.adapters"
                :exports="exports"
                :empty-label="t('pages.admin.integrations.empty_adapters')"
            />

            <NoticeBanner :title="t('pages.admin.integrations.bounded_title')">
                {{ t('pages.admin.integrations.bounded_runs') }}
            </NoticeBanner>

            <DataTable
                :title="t('pages.admin.integrations.recent_runs')"
                :rows="recentRunRows"
                :columns="recentRunColumns"
                row-key="rowKey"
                state-key="admin.integrations.recent-runs"
                export-key="admin.integrations.runs"
                :exports="exports"
                :empty-label="t('pages.admin.integrations.empty_runs')"
            />
        </PageStack>
    </AdminLayout>
</template>
