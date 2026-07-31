<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    IconActivity,
    IconAlertTriangle,
    IconApi,
    IconCircleCheck,
    IconPlugConnected,
    IconRefresh,
    IconShieldLock,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { formatDateTime } from '../../../Utils/formatters';

interface IntegrationRow extends Record<string, unknown> {
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

interface IntegrationRunRow {
    integrationKey: string | null;
    operation: string | null;
    correlationId: string | null;
    status: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    message: string | null;
}

interface IntegrationsSummary {
    registered: number;
    visible: number;
    enabled: number;
    openCircuits: number;
    running: number;
    failedLastRuns: number;
}

const props = defineProps<{
    integrations: IntegrationRow[];
    summary: IntegrationsSummary;
    filterOptions: {
        scopes: string[];
    };
    externalApiEnabled: boolean;
    recentRuns: IntegrationRunRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['status', 'circuit', 'external_api', 'scope'];
const filterDefaults = {
    status: 'all',
    circuit: 'all',
    external_api: 'all',
    scope: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<IntegrationRow[]>(() =>
    props.integrations.map((integration) => ({
        ...integration,
        circuitState: circuitLabel(integration.circuitState),
        lastRunStatus: statusLabel(integration.lastRunStatus),
    })),
);
const columns = computed<DataTableColumn<IntegrationRow>[]>(() => [
    { key: 'name', label: t('pages.admin.integrations.table.integration') },
    { key: 'key', label: t('pages.admin.integrations.table.key') },
    { key: 'sourceOfTruth', label: t('pages.admin.integrations.table.source_of_truth') },
    { key: 'providedScopes', label: t('pages.admin.integrations.table.scopes'), format: 'list' },
    { key: 'enabled', label: t('pages.admin.integrations.table.enabled'), format: 'boolean' },
    { key: 'circuitState', label: t('pages.admin.integrations.table.circuit'), format: 'status' },
    { key: 'lastRunStatus', label: t('pages.admin.integrations.table.last_run'), format: 'status' },
    { key: 'lastSuccessAt', label: t('pages.admin.integrations.table.last_success'), format: 'datetime' },
    { key: 'lastErrorAt', label: t('pages.admin.integrations.table.last_error'), format: 'datetime' },
    { key: 'externalApiEnabled', label: t('pages.admin.integrations.table.external_api'), format: 'boolean', hidden: true },
    { key: 'lastErrorMessage', label: t('pages.admin.integrations.table.last_error_message'), hidden: true },
    { key: 'adapterClass', label: t('pages.admin.integrations.table.adapter'), hidden: true },
    { key: 'requiredModules', label: t('pages.admin.integrations.table.required_modules'), format: 'list', hidden: true },
    { key: 'optionalModules', label: t('pages.admin.integrations.table.optional_modules'), format: 'list', hidden: true },
    { key: 'lastRunAt', label: t('pages.admin.integrations.table.last_run_at'), format: 'datetime', hidden: true },
]);
const actions = computed<DataTableAction<IntegrationRow>[]>(() => [
    {
        key: 'test',
        label: t('pages.admin.integrations.actions.test_connection'),
        method: 'post',
        href: (integration) => `/admin/integrations/${encodeURIComponent(integration.key)}/test`,
        tone: 'info',
    },
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.integrations.filters.any_status') },
    { value: 'enabled', label: t('pages.admin.integrations.filters.enabled') },
    { value: 'disabled', label: t('pages.admin.integrations.filters.disabled') },
]);
const circuitOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.integrations.filters.any_circuit') },
    { value: 'closed', label: circuitLabel('closed') },
    { value: 'open', label: circuitLabel('open') },
    { value: 'half_open', label: circuitLabel('half_open') },
]);
const externalApiOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.integrations.filters.any_external_api') },
    { value: 'enabled', label: t('pages.admin.integrations.filters.enabled') },
    { value: 'disabled', label: t('pages.admin.integrations.filters.disabled') },
]);
const scopeOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.scopes, t('pages.admin.integrations.filters.any_scope')),
);
const tableFilters = computed(() => filterValues());
const externalApiTone = computed(() => (props.externalApiEnabled ? 'amber' : 'zinc'));
const externalApiStatus = computed(() =>
    props.externalApiEnabled ? t('pages.admin.integrations.external_api.enabled') : t('pages.admin.integrations.external_api.disabled'),
);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        status: String(props.table.state.filters?.status ?? 'all'),
        circuit: String(props.table.state.filters?.circuit ?? 'all'),
        external_api: String(props.table.state.filters?.external_api ?? 'all'),
        scope: String(props.table.state.filters?.scope ?? 'all'),
    };
}

function allOptions(values: string[], label: string): FormSelectOption[] {
    return [
        { value: 'all', label },
        ...values.map((value) => ({
            value,
            label: value,
        })),
    ];
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function circuitLabel(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const keys: Record<string, string> = {
        closed: 'pages.admin.integrations.circuit.closed',
        half_open: 'pages.admin.integrations.circuit.half_open',
        open: 'pages.admin.integrations.circuit.open',
    };

    return keys[value] === undefined ? value : t(keys[value]);
}

function statusLabel(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const keys: Record<string, string> = {
        failed: 'statuses.failed',
        running: 'statuses.running',
        succeeded: 'statuses.succeeded',
    };

    return keys[value] === undefined ? value : t(keys[value]);
}

function runStartedAtLabel(run: IntegrationRunRow): string {
    return formatDateTime(run.startedAt, locale.value);
}
</script>

<template>
    <Head :title="t('pages.admin.integrations.head_title')" />
    <AdminLayout :title="t('pages.admin.integrations.title')" :title-icon="IconPlugConnected">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.registered')"
                    :value="summary.registered"
                    :icon="IconPlugConnected"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.visible')"
                    :value="summary.visible"
                    :icon="IconActivity"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.enabled')"
                    :value="summary.enabled"
                    :icon="IconCircleCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.open_circuits')"
                    :value="summary.openCircuits"
                    :icon="IconAlertTriangle"
                    :tone="summary.openCircuits > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.running')"
                    :value="summary.running"
                    :icon="IconRefresh"
                    :tone="summary.running > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.integrations.metric.failed_24h')"
                    :value="summary.failedLastRuns"
                    :icon="IconShieldLock"
                    :tone="summary.failedLastRuns > 0 ? 'rose' : 'zinc'"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.integrations.external_api.title')" :icon="IconApi" :tone="externalApiTone">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                        {{ t('pages.admin.integrations.external_api.global_access') }}
                    </span>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            externalApiEnabled
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
                                : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200'
                        "
                    >
                        {{ externalApiStatus }}
                    </span>
                </div>
            </SurfaceCard>

            <FilterPanel
                :title="t('pages.admin.integrations.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.status" :label="t('pages.admin.integrations.filters.status')" :options="statusOptions" />
                    <FormSelect
                        v-model="filters.circuit"
                        :label="t('pages.admin.integrations.filters.circuit')"
                        :options="circuitOptions"
                    />
                    <FormSelect
                        v-model="filters.external_api"
                        :label="t('pages.admin.integrations.filters.external_api')"
                        :options="externalApiOptions"
                    />
                    <FormSelect v-model="filters.scope" :label="t('pages.admin.integrations.filters.scope')" :options="scopeOptions" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.integrations.adapters.title')"
                :rows="rows"
                :columns="columns"
                row-key="key"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.integrations.adapters.empty')"
            />

            <SurfaceCard :title="t('pages.admin.integrations.runs.title')" :icon="IconRefresh" tone="zinc">
                <div v-if="recentRuns.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('pages.admin.integrations.runs.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="text-left text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                            <tr>
                                <th class="px-0 py-2 pr-3">{{ t('pages.admin.integrations.table.integration') }}</th>
                                <th class="px-3 py-2">{{ t('pages.admin.integrations.table.operation') }}</th>
                                <th class="px-3 py-2">{{ t('pages.admin.integrations.table.status') }}</th>
                                <th class="px-3 py-2">{{ t('pages.admin.integrations.table.started') }}</th>
                                <th class="px-3 py-2">{{ t('pages.admin.integrations.table.message') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr
                                v-for="run in recentRuns"
                                :key="`${run.integrationKey ?? 'integration'}-${run.correlationId ?? run.startedAt}`"
                            >
                                <td class="px-0 py-2 pr-3 font-medium text-zinc-900 dark:text-zinc-100">{{ run.integrationKey ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-200">{{ run.operation ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-200">{{ statusLabel(run.status) }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ runStartedAtLabel(run) }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ run.message ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </SurfaceCard>
        </PageStack>
    </AdminLayout>
</template>
