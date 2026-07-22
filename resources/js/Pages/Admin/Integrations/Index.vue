<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconActivityHeartbeat, IconAlertTriangle, IconPlugConnected, IconRotateClockwise } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed } from 'vue';

import SurfaceCard from '../../../Components/SurfaceCard.vue';
import DataTable from '../../../Components/DataTable.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
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

const { t } = useTranslator('en');

const summaryItems = computed<{ label: string; value: string; icon: Component; tone: 'amber' | 'emerald' | 'rose' | 'teal' }[]>(() => [
    { label: 'Registered', value: String(props.summary.registered), icon: IconPlugConnected, tone: 'teal' },
    { label: 'Running', value: String(props.summary.running), icon: IconRotateClockwise, tone: 'amber' },
    {
        label: 'Open circuits',
        value: String(props.summary.openCircuits),
        icon: IconAlertTriangle,
        tone: props.summary.openCircuits > 0 ? 'rose' : 'emerald',
    },
    {
        label: 'Failed 24h',
        value: String(props.summary.failedLastRuns),
        icon: IconActivityHeartbeat,
        tone: props.summary.failedLastRuns > 0 ? 'rose' : 'emerald',
    },
]);

const integrationColumns: DataTableColumn<IntegrationRecord>[] = [
    { key: 'name', label: 'Integration' },
    { key: 'key', label: 'Key' },
    { key: 'sourceOfTruth', label: 'Source of truth' },
    { key: 'adapterClass', label: 'Adapter', hidden: true },
    { key: 'circuitState', label: 'Circuit', format: 'severity' },
    { key: 'lastSuccessAt', label: 'Last success', format: 'datetime' },
    { key: 'lastErrorAt', label: 'Last error', format: 'datetime' },
    { key: 'lastErrorMessage', label: 'Last error message', hidden: true },
    { key: 'externalApiEnabled', label: 'External API', format: 'boolean', hidden: true },
];

const integrationActions: DataTableAction<IntegrationRecord>[] = [
    {
        key: 'test',
        label: 'Test connection',
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
    { key: 'integrationKey', label: 'Integration' },
    { key: 'operation', label: 'Operation' },
    { key: 'status', label: 'Status', format: 'severity' },
    { key: 'startedAt', label: 'Started', format: 'datetime' },
    { key: 'finishedAt', label: 'Finished', format: 'datetime', hidden: true },
    { key: 'correlationId', label: 'Correlation' },
    { key: 'message', label: 'Message', hidden: true },
];
</script>

<template>
    <Head :title="t('pages.admin.integrations.head_title')" />
    <AdminLayout :title="t('pages.admin.integrations.title')" :title-icon="IconPlugConnected">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard
                title="External API boundary"
                :icon="IconPlugConnected"
                :subtitle="`Global access: ${externalApiEnabled ? 'enabled' : 'disabled'}`"
                :tone="externalApiEnabled ? 'amber' : 'emerald'"
            >
                <template #actions>
                    <SeverityBadge
                        :value="externalApiEnabled ? 'warning' : 'success'"
                        :label="externalApiEnabled ? 'enabled' : 'disabled'"
                    />
                </template>
            </SurfaceCard>

            <DataTable
                title="Integration adapters"
                :rows="integrationRows"
                :columns="integrationColumns"
                row-key="key"
                :actions="integrationActions"
                state-key="admin.integrations.adapters"
                export-key="admin.integrations.adapters"
                :exports="exports"
                empty-label="No integration adapters registered."
            />

            <DataTable
                title="Recent synchronization runs"
                :rows="recentRunRows"
                :columns="recentRunColumns"
                row-key="rowKey"
                state-key="admin.integrations.recent-runs"
                export-key="admin.integrations.runs"
                :exports="exports"
                empty-label="No synchronization runs recorded."
            />
        </PageStack>
    </AdminLayout>
</template>
