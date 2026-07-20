<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconFileText, IconListDetails, IconRefresh, IconSettingsAutomation, IconX } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import CardHeader from '../../../Components/CardHeader.vue';
import CodeViewer from '../../../Components/CodeViewer.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface Run {
    publicId: string;
    processKey: string;
    moduleKey: string;
    status: string;
    sourceType: string;
    stage: string | null;
    progressCurrent: number;
    progressTotal: number | null;
    progressLabel: string | null;
    counters: Record<string, number>;
    inputSnapshot: Record<string, unknown>;
    resultSummary: Record<string, unknown>;
    safeErrorSummary: string | null;
    queueName: string | null;
    correlationId: string;
    actor: string | null;
    team: string | null;
    createdAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    canRetry: boolean;
    canCancel: boolean;
}

interface Log {
    publicId: string;
    occurredAt: string;
    severity: string;
    eventType: string;
    stage: string | null;
    message: string;
    safeContext: Record<string, unknown>;
    rowNumber: number | null;
    errorCode: string | null;
    exceptionClass: string | null;
    correlationId: string;
}

interface ImportExecution {
    publicId: string;
    importKey: string;
    sourceType: string;
    apiReference: string | null;
    externalReference: string | null;
    mappingSnapshot: Record<string, unknown>;
    sourceMetadata: Record<string, unknown>;
    statistics: Record<string, unknown>;
    idempotencyKey: string | null;
    idempotencyState: string;
    errors: {
        publicId: string;
        rowNumber: number | null;
        fieldName: string | null;
        severity: string;
        errorCode: string;
        message: string;
    }[];
}

const props = defineProps<{ run: Run; logs: Log[]; importExecution: ImportExecution | null }>();

const draftSearch = ref('');
const draftSeverity = ref('all');
const draftStage = ref('all');
const draftType = ref('all');
const search = ref('');
const severity = ref('all');
const stage = ref('all');
const type = ref('all');
const reason = ref('Operator reviewed the run in Admin.');

const severities = computed(() => optionList(props.logs.map((log) => log.severity)));
const stages = computed(() => optionList(props.logs.map((log) => log.stage).filter((value): value is string => value !== null)));
const types = computed(() => optionList(props.logs.map((log) => log.eventType)));
const filteredLogs = computed(() => {
    const query = search.value.toLowerCase().trim();

    return props.logs.filter((log) => {
        if (severity.value !== 'all' && log.severity !== severity.value) return false;
        if (stage.value !== 'all' && log.stage !== stage.value) return false;
        if (type.value !== 'all' && log.eventType !== type.value) return false;
        if (query === '') return true;

        return [log.message, log.stage, log.eventType, log.errorCode, JSON.stringify(log.safeContext)]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

function optionList(values: string[]): { value: string; label: string }[] {
    return [{ value: 'all', label: 'All' }, ...[...new Set(values)].sort().map((value) => ({ value, label: value }))];
}

function applyFilters(): void {
    search.value = draftSearch.value;
    severity.value = draftSeverity.value;
    stage.value = draftStage.value;
    type.value = draftType.value;
}

function clearFilters(): void {
    draftSearch.value = '';
    draftSeverity.value = 'all';
    draftStage.value = 'all';
    draftType.value = 'all';
    applyFilters();
}

function retry(): void {
    router.post(`/admin/managed-processes/${props.run.publicId}/retry`, { reason: reason.value }, { preserveScroll: true });
}

function cancel(): void {
    router.post(`/admin/managed-processes/${props.run.publicId}/cancel`, { reason: reason.value }, { preserveScroll: true });
}

function statusSeverity(value: string): string {
    if (value === 'succeeded') return 'success';
    if (value === 'succeeded_with_warnings' || value === 'warning') return 'warning';
    if (value === 'failed' || value === 'cancelled' || value === 'error') return 'failed';
    return 'info';
}
</script>

<template>
    <Head :title="run.processKey" />
    <AdminLayout :title="run.processKey" :title-icon="IconSettingsAutomation">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-2">
                        <SeverityBadge :value="statusSeverity(run.status)" :label="run.status" />
                        <p class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ run.publicId }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ run.progressLabel ?? run.stage }} · {{ run.progressCurrent }}/{{ run.progressTotal ?? '?' }} ·
                            {{ run.actor ?? 'system' }} / {{ run.team ?? 'global' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <FormInput v-model="reason" label="Reason" class="min-w-72" />
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            :disabled="!run.canRetry"
                            @click="retry"
                        >
                            <IconRefresh aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" /> Retry
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                            :disabled="!run.canCancel"
                            @click="cancel"
                        >
                            <IconX aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" /> Cancel
                        </button>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-3">
                <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <CardHeader title="Counters" :icon="IconListDetails" />
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div v-for="(value, key) in run.counters" :key="key">
                            <dt class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ key }}</dt>
                            <dd class="font-medium text-zinc-950 dark:text-zinc-50">{{ value }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <CardHeader title="Input summary" :icon="IconFileText" />
                    <CodeViewer class="mt-3" :content="JSON.stringify(run.inputSnapshot, null, 2)" language="json" />
                </section>
                <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <CardHeader title="Result summary" :icon="IconFileText" />
                    <CodeViewer class="mt-3" :content="JSON.stringify(run.resultSummary, null, 2)" language="json" />
                </section>
            </div>

            <section
                v-if="importExecution"
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
            >
                <CardHeader title="Import detail" :icon="IconListDetails" />
                <div class="mt-3 grid gap-4 text-sm md:grid-cols-4">
                    <p><span class="text-zinc-500">Source</span><br />{{ importExecution.sourceType }}</p>
                    <p>
                        <span class="text-zinc-500">Idempotency</span><br />{{ importExecution.idempotencyKey }} /
                        {{ importExecution.idempotencyState }}
                    </p>
                    <p><span class="text-zinc-500">External ref</span><br />{{ importExecution.externalReference ?? 'n/a' }}</p>
                    <p><span class="text-zinc-500">Row errors</span><br />{{ importExecution.errors.length }}</p>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2">Row</th>
                                <th class="px-3 py-2">Field</th>
                                <th class="px-3 py-2">Code</th>
                                <th class="px-3 py-2">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                            <tr v-for="error in importExecution.errors" :key="error.publicId">
                                <td class="px-3 py-2">{{ error.rowNumber ?? 'n/a' }}</td>
                                <td class="px-3 py-2">{{ error.fieldName ?? 'row' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ error.errorCode }}</td>
                                <td class="px-3 py-2">{{ error.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <FilterPanel
                :summary="`Showing ${filteredLogs.length} of ${logs.length} process log events.`"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-4 lg:grid-cols-4">
                    <FormInput v-model="draftSearch" label="Search" placeholder="Message, error code, context" />
                    <FormSelect v-model="draftSeverity" label="Severity" :options="severities" />
                    <FormSelect v-model="draftStage" label="Stage" :options="stages" />
                    <FormSelect v-model="draftType" label="Event type" :options="types" />
                </div>
            </FilterPanel>

            <section class="space-y-3">
                <article
                    v-for="log in filteredLogs"
                    :key="log.publicId"
                    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <SeverityBadge :value="statusSeverity(log.severity)" :label="log.severity" />
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ log.occurredAt }}</span>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ log.eventType }}</span>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ log.stage ?? 'n/a' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-950 dark:text-zinc-50">{{ log.message }}</p>
                    <CodeViewer class="mt-3" :content="JSON.stringify(log.safeContext, null, 2)" language="json" />
                </article>
            </section>
        </section>
    </AdminLayout>
</template>
