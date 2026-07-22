<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconChevronDown, IconEye, IconFileText, IconListDetails, IconServer } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTableExportMenu from '../../../Components/DataTableExportMenu.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TextBadge from '../../../Components/TextBadge.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import UiState from '../../../Components/UiState.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableExportMeta } from '../../../Types/data-table';

interface LogEntry extends Record<string, unknown> {
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
    latestModifiedAt: string | null;
}

const props = defineProps<{
    logs: LogEntry[];
    summary: LogSummary;
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator();
const draftSearch = ref('');
const draftLevel = ref('all');
const draftModule = ref('all');
const draftDateFrom = ref('');
const draftDateTo = ref('');
const search = ref('');
const level = ref('all');
const module = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const expanded = ref<string | null>(props.logs[0]?.publicId ?? null);
const logExportColumns = [
    'occurredAt',
    'level',
    'channel',
    'environment',
    'module',
    'source',
    'eventName',
    'message',
    'correlationId',
    'requestId',
] as const;

const levels = computed(() => optionsFrom(props.logs.map((entry) => entry.level).filter(Boolean)));
const modules = computed(() => optionsFrom(props.logs.map((entry) => entry.module).filter(Boolean)));

const filteredLogs = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.logs.filter((entry) => {
        if (level.value !== 'all' && entry.level !== level.value) {
            return false;
        }

        if (module.value !== 'all' && entry.module !== module.value) {
            return false;
        }

        if (!isWithinDateRange(entry)) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return [
            entry.message,
            entry.details,
            entry.module,
            entry.source,
            entry.eventName,
            entry.correlationId,
            entry.requestId,
            entry.channel,
            entry.environment,
        ]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const summaryItems = computed<{ label: string; value: string; icon: Component }[]>(() => [
    { label: t('pages.admin.logs.metric.source'), value: props.summary.source, icon: IconServer },
    { label: t('pages.admin.logs.metric.file'), value: props.summary.pathLabel, icon: IconFileText },
    { label: t('pages.admin.logs.metric.loaded_entries'), value: String(props.summary.rows), icon: IconListDetails },
    { label: t('pages.admin.logs.metric.visible_entries'), value: String(filteredLogs.value.length), icon: IconEye },
]);

function optionsFrom(values: string[]): { value: string; label: string }[] {
    const unique = [...new Set(values)].sort((a, b) => a.localeCompare(b));

    return [{ value: 'all', label: t('pages.admin.logs.all') }, ...unique.map((value) => ({ value, label: value }))];
}

function levelTone(value: string): 'danger' | 'info' | 'neutral' | 'warning' {
    if (['error', 'critical', 'alert', 'emergency'].includes(value)) {
        return 'danger';
    }

    if (['warning', 'notice'].includes(value)) {
        return 'warning';
    }

    if (value === 'info') {
        return 'info';
    }

    return 'neutral';
}

function toggle(publicId: string): void {
    expanded.value = expanded.value === publicId ? null : publicId;
}

function applyFilters(): void {
    search.value = draftSearch.value;
    level.value = draftLevel.value;
    module.value = draftModule.value;
    dateFrom.value = draftDateFrom.value;
    dateTo.value = draftDateTo.value;
}

function clearFilters(): void {
    draftSearch.value = '';
    draftLevel.value = 'all';
    draftModule.value = 'all';
    draftDateFrom.value = '';
    draftDateTo.value = '';
    applyFilters();
}

function isWithinDateRange(entry: LogEntry): boolean {
    const timestamp = timestampFor(entry.occurredAt);

    if (timestamp === null) {
        return dateFrom.value === '' && dateTo.value === '';
    }

    if (dateFrom.value !== '' && timestamp < startOfDay(dateFrom.value)) {
        return false;
    }

    if (dateTo.value !== '' && timestamp > endOfDay(dateTo.value)) {
        return false;
    }

    return true;
}

function timestampFor(value: string): number | null {
    if (value === '') {
        return null;
    }

    const parsed = Date.parse(value.includes('T') ? value : value.replace(' ', 'T'));

    return Number.isNaN(parsed) ? null : parsed;
}

function startOfDay(value: string): number {
    return Date.parse(`${value}T00:00:00`);
}

function endOfDay(value: string): number {
    return Date.parse(`${value}T23:59:59.999`);
}
</script>

<template>
    <Head :title="t('pages.admin.logs.head_title')" />
    <AdminLayout :title="t('pages.admin.logs.title')" :title-icon="IconFileText">
        <PageStack>
            <MetricGrid :items="summaryItems" />
            <NoticeBanner :title="t('pages.admin.logs.bounded_title')">
                {{ t('pages.admin.logs.bounded') }}
            </NoticeBanner>

            <FilterPanel
                :summary="t('pages.admin.logs.loaded_summary', { visible: filteredLogs.length, loaded: props.summary.rows })"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div
                    class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormInput
                        v-model="draftSearch"
                        :label="t('pages.admin.logs.search')"
                        :placeholder="t('pages.admin.logs.search_placeholder')"
                    />
                    <FormSelect v-model="draftLevel" :label="t('pages.admin.logs.level')" :options="levels" />
                    <FormSelect v-model="draftModule" :label="t('pages.admin.logs.module')" :options="modules" />
                    <FormDateInput v-model="draftDateFrom" :label="t('pages.admin.logs.from_date')" />
                    <FormDateInput v-model="draftDateTo" :label="t('pages.admin.logs.to_date')" />
                </div>
            </FilterPanel>

            <section class="space-y-3">
                <div class="flex justify-end">
                    <DataTableExportMenu
                        table-key="admin.logs"
                        :exports="exports"
                        :columns="[...logExportColumns]"
                        :column-order="[...logExportColumns]"
                        :filters="{ level, module, from: dateFrom, to: dateTo }"
                        :search="search"
                        sort="occurredAt"
                        direction="desc"
                    />
                </div>

                <SurfaceCard
                    v-for="entry in filteredLogs"
                    :key="entry.publicId"
                    :aria-label="t('pages.admin.logs.entry_aria', { publicId: entry.publicId })"
                    :padded="false"
                    overflow="hidden"
                >
                    <button
                        type="button"
                        class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-4 px-4 py-3 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-900/70"
                        :aria-expanded="expanded === entry.publicId"
                        @click="toggle(entry.publicId)"
                    >
                        <span class="min-w-0 space-y-2">
                            <span class="flex flex-wrap items-center gap-2">
                                <TextBadge :label="entry.level || t('pages.admin.logs.unknown')" :tone="levelTone(entry.level)" uppercase />
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{
                                    entry.occurredAt || t('pages.admin.logs.line_value', { line: entry.line })
                                }}</span>
                                <span v-if="entry.module" class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{
                                    entry.module
                                }}</span>
                                <span v-if="entry.source" class="text-xs text-zinc-500 dark:text-zinc-400">{{ entry.source }}</span>
                                <span v-if="entry.eventName" class="text-xs text-zinc-500 dark:text-zinc-400">{{ entry.eventName }}</span>
                            </span>
                            <Tooltip
                                :text="entry.message || t('pages.admin.logs.no_message')"
                                placement="top"
                                align="start"
                                full-width
                                wide
                            >
                                <span class="block truncate text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ entry.message || t('pages.admin.logs.no_message') }}
                                </span>
                            </Tooltip>
                        </span>
                        <IconChevronDown
                            aria-hidden="true"
                            class="mt-1 h-5 w-5 text-zinc-400 transition"
                            :class="{ 'rotate-180': expanded === entry.publicId }"
                            :stroke-width="1.8"
                        />
                    </button>

                    <div v-if="expanded === entry.publicId" class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-800">
                        <dl class="grid gap-3 text-xs sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.logs.line') }}</dt>
                                <dd class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ entry.line }}</dd>
                            </div>
                            <div v-if="entry.eventName">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ t('pages.admin.logs.event_name') }}
                                </dt>
                                <dd class="mt-1 break-all text-zinc-800 dark:text-zinc-200">{{ entry.eventName }}</dd>
                            </div>
                            <div v-if="entry.source">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.logs.source') }}</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.source }}</dd>
                            </div>
                            <div v-if="entry.module">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.logs.module') }}</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.module }}</dd>
                            </div>
                            <div v-if="entry.correlationId">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ t('pages.admin.logs.correlation_id') }}
                                </dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ entry.correlationId }}</dd>
                            </div>
                            <div v-if="entry.requestId">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ t('pages.admin.logs.request_id') }}
                                </dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ entry.requestId }}</dd>
                            </div>
                            <div v-if="entry.channel">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ t('pages.admin.logs.channel') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.channel }}</dd>
                            </div>
                            <div v-if="entry.environment">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ t('pages.admin.logs.environment') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.environment }}</dd>
                            </div>
                        </dl>

                        <section class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.logs.full_message') }}
                            </p>
                            <p class="mt-2 break-words text-sm text-zinc-900 dark:text-zinc-100">
                                {{ entry.message || t('pages.admin.logs.no_message') }}
                            </p>
                        </section>

                        <CodeViewer v-if="entry.details" class="mt-4" :content="entry.details" language="log" max-height="max-h-[28rem]" />
                    </div>
                </SurfaceCard>

                <UiState v-if="filteredLogs.length === 0" variant="no-results" :title="t('pages.admin.logs.empty_filtered')" />
            </section>
        </PageStack>
    </AdminLayout>
</template>
