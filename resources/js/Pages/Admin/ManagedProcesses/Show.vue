<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconFileText, IconListDetails, IconRefresh, IconSettingsAutomation, IconX } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import SurfaceCard from '../../../Components/SurfaceCard.vue';
import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableColumn } from '../../../Types/data-table';

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

interface ImportError extends Record<string, unknown> {
    publicId: string;
    rowNumber: number | null;
    fieldName: string | null;
    severity: string;
    errorCode: string;
    message: string;
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
    errors: ImportError[];
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
const importErrorColumns: DataTableColumn<ImportError>[] = [
    { key: 'rowNumber', label: 'Row', format: 'number' },
    { key: 'fieldName', label: 'Field' },
    { key: 'severity', label: 'Severity', format: 'severity' },
    { key: 'errorCode', label: 'Code' },
    { key: 'message', label: 'Message' },
];
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
        <PageStack>
            <SurfaceCard title="Run summary" :icon="IconSettingsAutomation">
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
                        <FormButton type="button" tone="neutral" :icon="IconRefresh" :disabled="!run.canRetry" @click="retry">
                            Retry
                        </FormButton>
                        <FormButton type="button" tone="danger" :icon="IconX" :disabled="!run.canCancel" @click="cancel">
                            Cancel
                        </FormButton>
                    </div>
                </div>
            </SurfaceCard>

            <div class="grid gap-5 xl:grid-cols-3">
                <SurfaceCard title="Counters" :icon="IconListDetails">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div v-for="(value, key) in run.counters" :key="key">
                            <dt class="text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ key }}</dt>
                            <dd class="font-medium text-zinc-950 dark:text-zinc-50">{{ value }}</dd>
                        </div>
                    </dl>
                </SurfaceCard>
                <SurfaceCard title="Input summary" :icon="IconFileText">
                    <CodeViewer :content="JSON.stringify(run.inputSnapshot, null, 2)" language="json" />
                </SurfaceCard>
                <SurfaceCard title="Result summary" :icon="IconFileText">
                    <CodeViewer :content="JSON.stringify(run.resultSummary, null, 2)" language="json" />
                </SurfaceCard>
            </div>

            <SurfaceCard v-if="importExecution" title="Import detail" :icon="IconListDetails">
                <div class="grid gap-4 text-sm md:grid-cols-4">
                    <p><span class="text-zinc-500">Source</span><br />{{ importExecution.sourceType }}</p>
                    <p>
                        <span class="text-zinc-500">Idempotency</span><br />{{ importExecution.idempotencyKey }} /
                        {{ importExecution.idempotencyState }}
                    </p>
                    <p><span class="text-zinc-500">External ref</span><br />{{ importExecution.externalReference ?? 'n/a' }}</p>
                    <p><span class="text-zinc-500">Row errors</span><br />{{ importExecution.errors.length }}</p>
                </div>
                <DataTable
                    class="mt-4"
                    title="Import row errors"
                    :rows="importExecution.errors"
                    :columns="importErrorColumns"
                    row-key="publicId"
                    state-key="admin.managed-processes.import-errors"
                    empty-label="No import row errors were recorded."
                />
            </SurfaceCard>

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
                <SurfaceCard v-for="log in filteredLogs" :key="log.publicId" :aria-label="`Process log ${log.publicId}`">
                    <div class="flex flex-wrap items-center gap-2">
                        <SeverityBadge :value="statusSeverity(log.severity)" :label="log.severity" />
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ log.occurredAt }}</span>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ log.eventType }}</span>
                        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ log.stage ?? 'n/a' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-zinc-950 dark:text-zinc-50">{{ log.message }}</p>
                    <CodeViewer class="mt-3" :content="JSON.stringify(log.safeContext, null, 2)" language="json" />
                </SurfaceCard>
            </section>
        </PageStack>
    </AdminLayout>
</template>
