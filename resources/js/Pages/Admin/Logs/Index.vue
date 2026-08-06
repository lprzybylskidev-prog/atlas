<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconBug, IconFileText, IconInfoCircle, IconListDetails } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTableExportMenu from '../../../Components/DataTableExportMenu.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableExportMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatDateTime, formatStatus } from '../../../Utils/formatters';
import { moduleLabel } from '../../../Utils/moduleLabels';

interface LogEntry {
    publicId: string;
    line: string;
    occurredAt: string;
    level: string;
    channel: string;
    environment: string;
    module: string;
    source: string;
    eventName: string;
    correlationId: string;
    requestId: string;
    message: string;
    details: string;
}

interface LogSummary {
    source: string;
    pathLabel: string;
    rows: number;
    visible: number;
    errors: number;
    warnings: number;
    withDetails: number;
    files: number;
    latestModifiedAt: string | null;
}

interface LogFileOption {
    name: string;
    size: number;
    latestModifiedAt: string | null;
}

const props = defineProps<{
    logs: LogEntry[];
    summary: LogSummary;
    filters: {
        file: string;
        level: string;
        module: string;
        source: string;
        from: string;
        to: string;
        search: string;
    };
    filterOptions: {
        files: LogFileOption[];
        levels: string[];
        modules: string[];
        sources: string[];
    };
    tableKey: string;
    exports: DataTableExportMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['file', 'level', 'module', 'source', 'from', 'to', 'search'];
const filterDefaults = computed(() => ({
    file: props.summary.pathLabel,
    level: 'all',
    module: 'all',
    source: 'all',
    from: '',
    to: '',
    search: '',
}));
const filters = ref({ ...filterDefaults.value, ...props.filters });
const selectedLogId = ref(props.logs[0]?.publicId ?? '');
const exportColumns = [
    'occurredAt',
    'level',
    'environment',
    'module',
    'source',
    'eventName',
    'correlationId',
    'requestId',
    'message',
    'details',
];

const displayLogs = computed<LogEntry[]>(() =>
    props.logs.map((log) => ({
        ...log,
        module: moduleLabel(log.module, t),
        source: readableLogToken(log.source),
        eventName: readableLogToken(log.eventName),
    })),
);
const selectedLog = computed<LogEntry | null>(
    () => displayLogs.value.find((log) => log.publicId === selectedLogId.value) ?? displayLogs.value[0] ?? null,
);
const fileOptions = computed<FormSelectOption[]>(() =>
    props.filterOptions.files.map((file) => ({
        value: file.name,
        label: file.name,
    })),
);
const levelOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.levels, t('pages.admin.logs.filters.any_level'), levelLabel),
);
const moduleOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.modules, t('pages.admin.logs.filters.any_module'), (module) => moduleLabel(module, t)),
);
const sourceOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.sources, t('pages.admin.logs.filters.any_source'), readableLogToken),
);
const latestModified = computed(() => formatDateTime(props.summary.latestModifiedAt, locale.value));

watch(
    () => props.filters,
    () => {
        filters.value = { ...filterDefaults.value, ...props.filters };
    },
);

watch(
    () => props.logs,
    () => {
        if (!props.logs.some((log) => log.publicId === selectedLogId.value)) {
            selectedLogId.value = props.logs[0]?.publicId ?? '';
        }
    },
);

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults.value);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults.value };
    clearTableFilters(filterKeys);
}

function levelLabel(level: string): string {
    const keys: Record<string, string> = {
        alert: 'pages.admin.logs.level.alert',
        critical: 'pages.admin.logs.level.critical',
        debug: 'pages.admin.logs.level.debug',
        emergency: 'pages.admin.logs.level.emergency',
        error: 'pages.admin.logs.level.error',
        info: 'pages.admin.logs.level.info',
        notice: 'pages.admin.logs.level.notice',
        unknown: 'pages.admin.logs.level.unknown',
        warning: 'pages.admin.logs.level.warning',
    };

    return keys[level] === undefined ? formatStatus(level) : t(keys[level]);
}

function readableLogToken(value: string): string {
    return value === '' ? '' : formatStatus(value);
}

function levelTone(level: string): 'danger' | 'info' | 'neutral' | 'success' | 'warning' {
    if (['alert', 'critical', 'emergency', 'error'].includes(level)) {
        return 'danger';
    }

    if (level === 'warning') {
        return 'warning';
    }

    if (level === 'info' || level === 'notice') {
        return 'info';
    }

    return 'neutral';
}

function occurredAtLabel(log: LogEntry): string {
    return formatDateTime(log.occurredAt, locale.value);
}

function compactMeta(log: LogEntry): string {
    return [log.module, log.source, log.eventName].filter((value) => value !== '').join(' / ');
}

function detailContent(log: LogEntry | null): string {
    if (log === null) {
        return '';
    }

    const lines = [`[${occurredAtLabel(log)}] ${levelLabel(log.level)}: ${log.message}`, log.details].filter((value) => value !== '');

    return lines.join('\n');
}
</script>

<template>
    <Head :title="t('pages.admin.logs.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.logs.title')" :title-icon="IconFileText">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.files')"
                    :value="summary.files"
                    :icon="IconFileText"
                    tone="zinc"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.loaded')"
                    :value="summary.rows"
                    :icon="IconListDetails"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.errors')"
                    :value="summary.errors"
                    :icon="IconBug"
                    :tone="summary.errors > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.warnings')"
                    :value="summary.warnings"
                    :icon="IconAlertTriangle"
                    :tone="summary.warnings > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.logs.metric.with_details')"
                    :value="summary.withDetails"
                    :icon="IconInfoCircle"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.logs.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <FormSelect v-model="filters.file" :label="t('pages.admin.logs.filters.file')" :options="fileOptions" />
                    <FormSelect v-model="filters.level" :label="t('pages.admin.logs.filters.level')" :options="levelOptions" />
                    <FormSelect v-model="filters.module" :label="t('pages.admin.logs.filters.module')" :options="moduleOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.logs.filters.source')" :options="sourceOptions" />
                    <FormDateInput v-model="filters.from" :label="t('pages.admin.logs.filters.from')" />
                    <FormDateInput v-model="filters.to" :label="t('pages.admin.logs.filters.to')" />
                    <FormInput v-model="filters.search" :label="t('pages.admin.logs.filters.search')" />
                </div>
            </FilterPanel>

            <SurfaceCard :title="t('pages.admin.logs.viewer.title')" :icon="IconFileText" tone="zinc" overflow="hidden">
                <template #actions>
                    <DataTableExportMenu
                        :table-key="tableKey"
                        :exports="exports"
                        :columns="exportColumns"
                        :column-order="exportColumns"
                        :filters="filters"
                        sort="occurredAt"
                        direction="desc"
                        :ui-locale="locale"
                    />
                </template>

                <div class="mb-4 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span>{{ t('pages.admin.logs.viewer.file', { file: summary.pathLabel }) }}</span>
                    <span v-if="summary.latestModifiedAt">{{ t('pages.admin.logs.viewer.modified', { date: latestModified }) }}</span>
                </div>

                <div v-if="displayLogs.length === 0" class="py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('pages.admin.logs.viewer.empty') }}
                </div>

                <div v-else class="grid min-h-[34rem] gap-4 xl:grid-cols-[minmax(20rem,28rem)_1fr]">
                    <div class="max-h-[42rem] overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <button
                            v-for="log in displayLogs"
                            :key="log.publicId"
                            type="button"
                            class="block w-full border-b border-zinc-200 px-3 py-3 text-left transition last:border-b-0 hover:bg-zinc-50 focus-visible:outline focus-visible:outline-amber-500 dark:border-zinc-800 dark:hover:bg-zinc-900"
                            :class="{ 'bg-teal-50/70 dark:bg-teal-950/30': selectedLog?.publicId === log.publicId }"
                            @click="selectedLogId = log.publicId"
                        >
                            <div class="flex min-w-0 items-center justify-between gap-2">
                                <StatusBadge :label="levelLabel(log.level)" :tone="levelTone(log.level)" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ occurredAtLabel(log) }}</span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ log.message }}</p>
                            <p v-if="compactMeta(log)" class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ compactMeta(log) }}
                            </p>
                        </button>
                    </div>

                    <div class="min-w-0 space-y-4">
                        <div v-if="selectedLog" class="grid gap-2 text-sm md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.correlation_id') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.correlationId || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.request_id') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.requestId || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.channel') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.channel || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.environment') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.environment || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.module') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.module || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.logs.details.line') }}
                                </p>
                                <p class="truncate text-zinc-900 dark:text-zinc-100">{{ selectedLog.line || '-' }}</p>
                            </div>
                        </div>

                        <CodeViewer
                            :title="t('pages.admin.logs.details.payload')"
                            :content="detailContent(selectedLog)"
                            language="log"
                            max-height="max-h-[34rem]"
                            :copy-label="t('actions.copy')"
                            :copied-label="t('actions.copied')"
                            :wrap-label="t('actions.wrap_lines')"
                            :unwrap-label="t('actions.unwrap_lines')"
                        />
                    </div>
                </div>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
