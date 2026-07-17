<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconCalendarTime, IconChevronDown, IconEye, IconFileText, IconListDetails, IconServer } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import AdminFilterPanel from '../../../Components/AdminFilterPanel.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

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
}>();

const { t } = useTranslator('en');
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
    { label: 'Source', value: props.summary.source, icon: IconServer },
    { label: 'File', value: props.summary.pathLabel, icon: IconFileText },
    { label: 'Loaded entries', value: String(props.summary.rows), icon: IconListDetails },
    { label: 'Visible entries', value: String(filteredLogs.value.length), icon: IconEye },
]);

function optionsFrom(values: string[]): { value: string; label: string }[] {
    const unique = [...new Set(values)].sort((a, b) => a.localeCompare(b));

    return [{ value: 'all', label: 'All' }, ...unique.map((value) => ({ value, label: value }))];
}

function levelClass(value: string): string {
    if (['error', 'critical', 'alert', 'emergency'].includes(value)) {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200';
    }

    if (['warning', 'notice'].includes(value)) {
        return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200';
    }

    if (value === 'info') {
        return 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200';
    }

    return 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300';
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

function detailLines(value: string): { key: string; text: string; kind: 'frame' | 'heading' | 'plain' }[] {
    return value.split(/\r?\n/).map((line, index) => ({
        key: `${index}-${line}`,
        text: line,
        kind: line.startsWith('#') ? 'frame' : line.startsWith('[') && line.endsWith(']') ? 'heading' : 'plain',
    }));
}
</script>

<template>
    <Head :title="t('pages.admin.logs.head_title')" />
    <AdminLayout :title="t('pages.admin.logs.title')" :title-icon="IconFileText">
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

            <AdminFilterPanel
                :summary="`Showing ${filteredLogs.length} of ${props.summary.rows} loaded log entries.`"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div
                    class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormInput v-model="draftSearch" label="Search" placeholder="Message, correlation ID, module, source" />
                    <FormSelect v-model="draftLevel" label="Level" :options="levels" />
                    <FormSelect v-model="draftModule" label="Module" :options="modules" />
                    <FormInput
                        v-model="draftDateFrom"
                        type="date"
                        label="From date"
                        placeholder="YYYY-MM-DD"
                        :leading-icon="IconCalendarTime"
                    />
                    <FormInput
                        v-model="draftDateTo"
                        type="date"
                        label="To date"
                        placeholder="YYYY-MM-DD"
                        :leading-icon="IconCalendarTime"
                    />
                </div>
            </AdminFilterPanel>

            <section class="space-y-3">
                <article
                    v-for="entry in filteredLogs"
                    :key="entry.publicId"
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <button
                        type="button"
                        class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-4 px-4 py-3 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-900/70"
                        :aria-expanded="expanded === entry.publicId"
                        @click="toggle(entry.publicId)"
                    >
                        <span class="min-w-0 space-y-2">
                            <span class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex h-6 items-center rounded-md border px-2 text-xs font-semibold uppercase"
                                    :class="levelClass(entry.level)"
                                >
                                    {{ entry.level || 'unknown' }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ entry.occurredAt || `line ${entry.line}` }}</span>
                                <span v-if="entry.module" class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{
                                    entry.module
                                }}</span>
                                <span v-if="entry.source" class="text-xs text-zinc-500 dark:text-zinc-400">{{ entry.source }}</span>
                                <span v-if="entry.eventName" class="text-xs text-zinc-500 dark:text-zinc-400">{{ entry.eventName }}</span>
                            </span>
                            <Tooltip :text="entry.message || 'No message'" placement="top" align="start" full-width wide>
                                <span class="block truncate text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ entry.message || 'No message' }}
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
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Line</dt>
                                <dd class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ entry.line }}</dd>
                            </div>
                            <div v-if="entry.eventName">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Event name</dt>
                                <dd class="mt-1 break-all text-zinc-800 dark:text-zinc-200">{{ entry.eventName }}</dd>
                            </div>
                            <div v-if="entry.source">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Source</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.source }}</dd>
                            </div>
                            <div v-if="entry.module">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Module</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.module }}</dd>
                            </div>
                            <div v-if="entry.correlationId">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Correlation ID</dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ entry.correlationId }}</dd>
                            </div>
                            <div v-if="entry.requestId">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Request ID</dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ entry.requestId }}</dd>
                            </div>
                            <div v-if="entry.channel">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Channel</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.channel }}</dd>
                            </div>
                            <div v-if="entry.environment">
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Environment</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ entry.environment }}</dd>
                            </div>
                        </dl>

                        <section class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
                            <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Full message</p>
                            <p class="mt-2 break-words text-sm text-zinc-900 dark:text-zinc-100">{{ entry.message || 'No message' }}</p>
                        </section>

                        <div
                            v-if="entry.details"
                            class="mt-4 max-h-[28rem] overflow-auto rounded-lg border border-zinc-200 bg-zinc-50 p-3 font-mono text-xs leading-5 text-zinc-800 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            <div
                                v-for="line in detailLines(entry.details)"
                                :key="line.key"
                                class="whitespace-pre-wrap break-words"
                                :class="{
                                    'mt-2 font-semibold text-teal-700 dark:text-teal-300': line.kind === 'heading',
                                    'pl-3 text-zinc-600 dark:text-zinc-300': line.kind === 'frame',
                                }"
                            >
                                {{ line.text }}
                            </div>
                        </div>
                    </div>
                </article>

                <section
                    v-if="filteredLogs.length === 0"
                    class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400"
                >
                    No log entries match the current filters.
                </section>
            </section>
        </section>
    </AdminLayout>
</template>
