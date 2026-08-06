<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconAlertTriangle, IconBan, IconDatabaseImport, IconListDetails, IconPlayerPlay, IconRefresh } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import ManagedProcessArea from '../../../Components/ManagedProcesses/ManagedProcessArea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import {
    managedProcessOptionsWithAll,
    jsonText,
    processSeverityLabel,
    processSourceLabel,
    processStatusLabel,
} from '../../../Composables/useManagedProcessUi';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import { useTranslator } from '../../../Localization/translator';
import { formatTimestamp } from '../../../Utils/formatters';
import { moduleLabel } from '../../../Utils/moduleLabels';
import type { DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';
import type { ManagedProcessFilterOptions, ManagedProcessLogRow, ManagedProcessRunRow } from '../../../Types/managed-processes';

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
    errors: ImportErrorRow[];
}

interface ImportErrorRow extends Record<string, unknown> {
    publicId: string;
    rowNumber: number | null;
    fieldName: string | null;
    severity: string;
    errorCode: string;
    message: string;
    safeContext: Record<string, unknown>;
}

const props = defineProps<{
    run: ManagedProcessRunRow;
    logs: ManagedProcessLogRow[];
    importExecution: ImportExecution | null;
    filterOptions: ManagedProcessFilterOptions;
    exports: DataTableExportMeta;
}>();

const { locale, t } = useTranslator();
const retryForm = useForm({ reason: '' });
const cancelForm = useForm({ reason: '' });
const filterKeys = ['severity', 'event', 'stage'];
const filterDefaults = { severity: 'all', event: 'all', stage: 'all' };
const filters = ref({ ...filterDefaults, ...filterValues() });

const errorColumns = computed<DataTableColumn<ImportErrorRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.managed_processes.table.public_id'), hidden: true },
    { key: 'rowNumber', label: t('pages.admin.managed_processes.table.row_number'), format: 'number' },
    { key: 'fieldName', label: t('pages.admin.managed_processes.table.field') },
    { key: 'severity', label: t('pages.admin.managed_processes.table.severity') },
    { key: 'errorCode', label: t('pages.admin.managed_processes.table.error_code') },
    { key: 'message', label: t('pages.admin.managed_processes.table.message') },
]);
const severityOptions = computed<FormSelectOption[]>(() =>
    managedProcessOptionsWithAll(
        props.filterOptions.severities ?? [],
        t('pages.admin.managed_processes.filters.any_severity'),
        (severity) => processSeverityLabel(severity, t),
    ),
);
const eventOptions = computed<FormSelectOption[]>(() =>
    managedProcessOptionsWithAll(props.filterOptions.eventTypes ?? [], t('pages.admin.managed_processes.filters.any_event')),
);
const stageOptions = computed<FormSelectOption[]>(() =>
    managedProcessOptionsWithAll(props.filterOptions.stages ?? [], t('pages.admin.managed_processes.filters.any_stage')),
);
const statusLabel = computed(() => processStatusLabel(props.run.status, t));
const sourceLabel = computed(() => processSourceLabel(props.run.sourceType, t));
const activeRun = computed(() => ['draft', 'queued', 'running', 'waiting'].includes(props.run.status));
const progressValue = computed(() => {
    if (props.run.progressTotal !== null && props.run.progressTotal > 0) {
        return `${props.run.progressCurrent}/${props.run.progressTotal}`;
    }

    return props.run.progressLabel ?? t('pages.admin.managed_processes.progress_unknown');
});
const logRows = computed(() =>
    props.logs.map((log) => ({
        ...log,
        severityLabel: processSeverityLabel(log.severity, t),
        occurredAtLabel: formatTimestamp(log.occurredAt, locale.value),
    })),
);
const importErrorRows = computed<ImportErrorRow[]>(() =>
    (props.importExecution?.errors ?? []).map((error) => ({
        ...error,
        severity: processSeverityLabel(error.severity, t),
    })),
);

function filterValues(): Record<string, string> {
    if (typeof window === 'undefined') {
        return { ...filterDefaults };
    }

    const query = new URLSearchParams(window.location.search);

    return {
        severity: query.get('severity') ?? 'all',
        event: query.get('event') ?? 'all',
        stage: query.get('stage') ?? 'all',
    };
}

function retryRun(): void {
    retryForm.post(`/admin/managed-processes/${encodeURIComponent(props.run.publicId)}/retry`, {
        preserveScroll: true,
        onSuccess: () => retryForm.reset(),
    });
}

function cancelRun(): void {
    cancelForm.post(`/admin/managed-processes/${encodeURIComponent(props.run.publicId)}/cancel`, {
        preserveScroll: true,
        onSuccess: () => cancelForm.reset(),
    });
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

let refreshTimer: number | undefined;

function refreshRun(): void {
    router.reload({
        only: ['run', 'logs', 'importExecution', 'filterOptions'],
    });
}

function startRefreshTimer(): void {
    if (refreshTimer !== undefined || !activeRun.value) {
        return;
    }

    refreshTimer = window.setInterval(refreshRun, 2000);
}

function stopRefreshTimer(): void {
    if (refreshTimer === undefined) {
        return;
    }

    window.clearInterval(refreshTimer);
    refreshTimer = undefined;
}

watch(activeRun, (active) => {
    if (active) {
        startRefreshTimer();

        return;
    }

    stopRefreshTimer();
});

onMounted(startRefreshTimer);
onBeforeUnmount(stopRefreshTimer);
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.show.head_title', { run: run.publicId })" />
    <ManagedProcessArea :title="t('pages.admin.managed_processes.show.title')" :current-path="`/admin/managed-processes/${run.publicId}`">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.process')"
                    :value="run.processKey"
                    :icon="IconPlayerPlay"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.status')"
                    :value="statusLabel"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.progress')"
                    :value="progressValue"
                    :icon="IconRefresh"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.source')"
                    :value="sourceLabel"
                    :icon="IconDatabaseImport"
                    tone="zinc"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.managed_processes.show.run_context')" :icon="IconListDetails" tone="sky">
                <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.module') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ moduleLabel(run.moduleKey, t) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.queue') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ run.queueName ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.actor') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ run.actor ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.team') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ run.team ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.started') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                            {{ formatTimestamp(run.startedAt, locale) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.finished') }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                            {{ formatTimestamp(run.finishedAt, locale) }}
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managed_processes.table.correlation_id') }}
                        </dt>
                        <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ run.correlationId }}</dd>
                    </div>
                </dl>
                <div
                    v-if="run.safeErrorSummary"
                    class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900 dark:bg-rose-950/30"
                >
                    <p class="flex items-center gap-2 text-sm font-semibold text-rose-800 dark:text-rose-200">
                        <IconAlertTriangle aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('pages.admin.managed_processes.table.safe_error_summary') }}
                    </p>
                    <p class="mt-1 text-sm text-rose-700 dark:text-rose-200">{{ run.safeErrorSummary }}</p>
                </div>
            </SurfaceCard>

            <div v-if="run.canRetry || run.canCancel" class="grid gap-4" :class="run.canRetry && run.canCancel ? 'lg:grid-cols-2' : ''">
                <SurfaceCard v-if="run.canRetry" :title="t('pages.admin.managed_processes.retry')" :icon="IconRefresh" tone="amber">
                    <AtlasForm :processing="retryForm.processing" @submit="retryRun">
                        <FormTextarea
                            v-model="retryForm.reason"
                            :label="t('pages.admin.managed_processes.reason')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.retry_reason')"
                            :error="retryForm.errors.reason"
                        />
                        <div class="mt-4 flex justify-end">
                            <FormButton type="submit" :icon="IconRefresh" :loading="retryForm.processing">
                                {{ t('pages.admin.managed_processes.retry') }}
                            </FormButton>
                        </div>
                    </AtlasForm>
                </SurfaceCard>

                <SurfaceCard v-if="run.canCancel" :title="t('pages.admin.managed_processes.cancel')" :icon="IconBan" tone="rose">
                    <AtlasForm :processing="cancelForm.processing" @submit="cancelRun">
                        <FormTextarea
                            v-model="cancelForm.reason"
                            :label="t('pages.admin.managed_processes.reason')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.cancel_reason')"
                            :error="cancelForm.errors.reason"
                        />
                        <div class="mt-4 flex justify-end">
                            <FormButton type="submit" tone="danger" :icon="IconBan" :loading="cancelForm.processing">
                                {{ t('pages.admin.managed_processes.cancel') }}
                            </FormButton>
                        </div>
                    </AtlasForm>
                </SurfaceCard>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.admin.managed_processes.show.input')" :icon="IconListDetails" tone="zinc">
                    <CodeViewer :content="jsonText(run.inputSnapshot)" language="json" />
                </SurfaceCard>
                <SurfaceCard :title="t('pages.admin.managed_processes.show.result')" :icon="IconListDetails" tone="zinc">
                    <CodeViewer :content="jsonText(run.resultSummary)" language="json" />
                </SurfaceCard>
            </div>

            <SurfaceCard
                v-if="importExecution"
                :title="t('pages.admin.managed_processes.show.import_context')"
                :icon="IconDatabaseImport"
                tone="teal"
            >
                <div class="flex flex-wrap gap-2">
                    <span
                        class="rounded-md bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-700 ring-1 ring-teal-200 dark:bg-teal-950/60 dark:text-teal-300 dark:ring-teal-900"
                    >
                        {{ t('pages.admin.managed_processes.import') }}: {{ importExecution.importKey }}
                    </span>
                    <span
                        class="rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:ring-sky-900"
                    >
                        {{ t('pages.admin.managed_processes.source') }}: {{ processSourceLabel(importExecution.sourceType, t) }}
                    </span>
                    <span
                        class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800"
                    >
                        {{ t('pages.admin.managed_processes.idempotency_state') }}: {{ importExecution.idempotencyState }}
                    </span>
                </div>
                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    <CodeViewer
                        :title="t('pages.admin.managed_processes.statistics')"
                        :content="jsonText(importExecution.statistics)"
                        language="json"
                    />
                    <CodeViewer
                        :title="t('pages.admin.managed_processes.show.source_metadata')"
                        :content="jsonText(importExecution.sourceMetadata)"
                        language="json"
                    />
                </div>
            </SurfaceCard>

            <DataTable
                v-if="importExecution"
                :title="t('pages.admin.managed_processes.show.import_errors')"
                :rows="importErrorRows"
                :columns="errorColumns"
                row-key="publicId"
                export-key="admin.managed-processes.import-row-errors"
                :exports="exports"
                :filters="{ run: run.publicId, import: importExecution.publicId }"
                :ui-locale="locale"
                :empty-label="t('pages.admin.managed_processes.show.no_import_errors')"
            />

            <FilterPanel
                :title="t('pages.admin.managed_processes.show.log_filters')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-3">
                    <FormSelect
                        v-model="filters.severity"
                        :label="t('pages.admin.managed_processes.table.severity')"
                        :options="severityOptions"
                    />
                    <FormSelect
                        v-model="filters.event"
                        :label="t('pages.admin.managed_processes.table.event_type')"
                        :options="eventOptions"
                    />
                    <FormSelect v-model="filters.stage" :label="t('pages.admin.managed_processes.table.stage')" :options="stageOptions" />
                </div>
            </FilterPanel>

            <SurfaceCard :title="t('pages.admin.managed_processes.show.timeline')" :icon="IconListDetails" tone="teal">
                <ol v-if="logRows.length > 0" class="space-y-3">
                    <li
                        v-for="log in logRows"
                        :key="log.publicId"
                        class="border-b border-zinc-200 pb-3 last:border-b-0 dark:border-zinc-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <SeverityBadge :value="log.severity" :label="log.severityLabel" />
                                    <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ log.eventType }}</span>
                                    <span v-if="log.stage" class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{
                                        log.stage
                                    }}</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ log.message }}</p>
                            </div>
                            <time class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ log.occurredAtLabel }}</time>
                        </div>
                        <CodeViewer
                            v-if="Object.keys(log.safeContext).length > 0"
                            class="mt-3"
                            :content="jsonText(log.safeContext)"
                            language="json"
                            max-height="max-h-48"
                        />
                    </li>
                </ol>
                <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.managed_processes.show.no_logs') }}</p>
            </SurfaceCard>
        </PageStack>
    </ManagedProcessArea>
</template>
