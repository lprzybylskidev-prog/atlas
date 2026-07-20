<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconActivityHeartbeat, IconAlertTriangle, IconPlugConnected, IconRotateClockwise } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import SeverityBadge from '../../../Components/SeverityBadge.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface IntegrationRecord {
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

interface RecentRun {
    integrationKey: string | null;
    operation: string | null;
    correlationId: string | null;
    status: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    message: string | null;
}

const props = defineProps<{
    integrations: IntegrationRecord[];
    summary: IntegrationSummary;
    externalApiEnabled: boolean;
    recentRuns: RecentRun[];
}>();

const { t } = useTranslator('en');
const testing = ref<string | null>(null);

const summaryItems = computed<{ label: string; value: string; icon: Component; severity: string }[]>(() => [
    { label: 'Registered', value: String(props.summary.registered), icon: IconPlugConnected, severity: 'info' },
    { label: 'Running', value: String(props.summary.running), icon: IconRotateClockwise, severity: 'warning' },
    {
        label: 'Open circuits',
        value: String(props.summary.openCircuits),
        icon: IconAlertTriangle,
        severity: props.summary.openCircuits > 0 ? 'failed' : 'success',
    },
    {
        label: 'Failed 24h',
        value: String(props.summary.failedLastRuns),
        icon: IconActivityHeartbeat,
        severity: props.summary.failedLastRuns > 0 ? 'failed' : 'success',
    },
]);

function testConnection(integration: IntegrationRecord): void {
    testing.value = integration.key;
    router.post(`/admin/integrations/${integration.key}/test`, {}, { preserveScroll: true, onFinish: () => (testing.value = null) });
}

function statusSeverity(value: string | null): string {
    if (value === 'succeeded' || value === 'closed') {
        return 'success';
    }

    if (value === 'failed' || value === 'open') {
        return 'failed';
    }

    return 'warning';
}
</script>

<template>
    <Head :title="t('pages.admin.integrations.head_title')" />
    <AdminLayout :title="t('pages.admin.integrations.title')" :title-icon="IconPlugConnected">
        <section class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <section
                    v-for="item in summaryItems"
                    :key="item.label"
                    class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200"
                    >
                        <component :is="item.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    </span>
                    <span class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ item.label }}</p>
                        <p class="mt-1 truncate text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ item.value }}</p>
                    </span>
                </section>
            </div>

            <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">External API boundary</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            Global access: {{ externalApiEnabled ? 'enabled' : 'disabled' }}
                        </p>
                    </div>
                    <SeverityBadge
                        :value="externalApiEnabled ? 'warning' : 'success'"
                        :label="externalApiEnabled ? 'enabled' : 'disabled'"
                    />
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Integration</th>
                                <th class="px-4 py-3">Source of truth</th>
                                <th class="px-4 py-3">Circuit</th>
                                <th class="px-4 py-3">Last success</th>
                                <th class="px-4 py-3">Last error</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                            <tr v-if="integrations.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No integration adapters registered.
                                </td>
                            </tr>
                            <tr v-for="integration in integrations" :key="integration.key" class="align-top">
                                <td class="max-w-[18rem] px-4 py-3">
                                    <p class="truncate font-medium text-zinc-950 dark:text-zinc-50">{{ integration.name }}</p>
                                    <p class="mt-1 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ integration.key }}</p>
                                </td>
                                <td class="max-w-[20rem] px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    <p class="truncate">{{ integration.sourceOfTruth }}</p>
                                    <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ integration.adapterClass }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <SeverityBadge
                                        :value="statusSeverity(integration.circuitState)"
                                        :label="integration.circuitState ?? 'closed'"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ integration.lastSuccessAt ?? 'Never' }}
                                </td>
                                <td class="max-w-[18rem] px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    <p class="truncate">{{ integration.lastErrorAt ?? 'Never' }}</p>
                                    <p v-if="integration.lastErrorMessage" class="mt-1 truncate text-xs text-red-600 dark:text-red-300">
                                        {{ integration.lastErrorMessage }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Tooltip text="Test connection">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-teal-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                            :disabled="testing === integration.key"
                                            @click="testConnection(integration)"
                                        >
                                            <IconRotateClockwise aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                        </button>
                                    </Tooltip>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <h2 class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Recent synchronization runs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Integration</th>
                                <th class="px-4 py-3">Operation</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Started</th>
                                <th class="px-4 py-3">Correlation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                            <tr v-if="recentRuns.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No synchronization runs recorded.
                                </td>
                            </tr>
                            <tr v-for="run in recentRuns" :key="`${run.integrationKey}-${run.correlationId}`">
                                <td class="px-4 py-3 font-medium text-zinc-950 dark:text-zinc-50">{{ run.integrationKey }}</td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ run.operation }}</td>
                                <td class="px-4 py-3">
                                    <SeverityBadge :value="statusSeverity(run.status)" :label="run.status ?? 'unknown'" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ run.startedAt }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ run.correlationId }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>
