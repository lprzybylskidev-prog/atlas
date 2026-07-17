<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconCalendarTime,
    IconChevronDown,
    IconDatabase,
    IconListDetails,
    IconRotateClockwise,
    IconServer,
} from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import AdminFilterPanel from '../../../Components/AdminFilterPanel.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import { useModal } from '../../../Composables/useModal';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface FailedJob {
    uuid: string;
    connection: string;
    queue: string;
    failedAt: string;
    displayName: string;
    jobClass: string;
    exceptionType: string;
    exceptionMessage: string;
    payload: string;
    exception: string;
}

interface QueueSummary {
    failedCount: number;
    visibleCount: number;
    queues: number;
    connections: number;
    latestFailedAt: string | null;
    oldestFailedAt: string | null;
}

const props = defineProps<{
    jobs: FailedJob[];
    summary: QueueSummary;
}>();

const { t } = useTranslator('en');
const modal = useModal();
const draftSearch = ref('');
const draftConnection = ref('all');
const draftQueue = ref('all');
const draftDateFrom = ref('');
const draftDateTo = ref('');
const search = ref('');
const connection = ref('all');
const queue = ref('all');
const dateFrom = ref('');
const dateTo = ref('');
const expanded = ref<string | null>(props.jobs[0]?.uuid ?? null);
const selected = ref<string[]>([]);
const retrying = ref(false);

const connections = computed(() => optionsFrom(props.jobs.map((job) => job.connection).filter(Boolean)));
const queues = computed(() => optionsFrom(props.jobs.map((job) => job.queue).filter(Boolean)));

const filteredJobs = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.jobs.filter((job) => {
        if (connection.value !== 'all' && job.connection !== connection.value) {
            return false;
        }

        if (queue.value !== 'all' && job.queue !== queue.value) {
            return false;
        }

        if (!isWithinDateRange(job)) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return [job.uuid, job.connection, job.queue, job.displayName, job.jobClass, job.exceptionType, job.exceptionMessage]
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const filteredUuids = computed(() => filteredJobs.value.map((job) => job.uuid));
const selectedVisibleCount = computed(() => selected.value.filter((uuid) => filteredUuids.value.includes(uuid)).length);
const allVisibleSelected = computed(() => filteredJobs.value.length > 0 && selectedVisibleCount.value === filteredJobs.value.length);
const partiallySelected = computed(() => selectedVisibleCount.value > 0 && !allVisibleSelected.value);

const summaryItems = computed<{ label: string; value: string; icon: Component }[]>(() => [
    { label: 'Failed jobs', value: String(props.summary.failedCount), icon: IconAlertTriangle },
    { label: 'Visible jobs', value: String(filteredJobs.value.length), icon: IconListDetails },
    { label: 'Queues', value: String(props.summary.queues), icon: IconDatabase },
    { label: 'Connections', value: String(props.summary.connections), icon: IconServer },
]);

function optionsFrom(values: string[]): { value: string; label: string }[] {
    const unique = [...new Set(values)].sort((left, right) => left.localeCompare(right));

    return [{ value: 'all', label: 'All' }, ...unique.map((value) => ({ value, label: value }))];
}

function applyFilters(): void {
    search.value = draftSearch.value;
    connection.value = draftConnection.value;
    queue.value = draftQueue.value;
    dateFrom.value = draftDateFrom.value;
    dateTo.value = draftDateTo.value;
}

function clearFilters(): void {
    draftSearch.value = '';
    draftConnection.value = 'all';
    draftQueue.value = 'all';
    draftDateFrom.value = '';
    draftDateTo.value = '';
    applyFilters();
}

function toggle(uuid: string): void {
    expanded.value = expanded.value === uuid ? null : uuid;
}

function toggleAllVisible(): void {
    if (allVisibleSelected.value) {
        selected.value = selected.value.filter((uuid) => !filteredUuids.value.includes(uuid));
        return;
    }

    selected.value = [...new Set([...selected.value, ...filteredUuids.value])];
}

async function retryOne(job: FailedJob): Promise<void> {
    const confirmed = await modal.confirm({
        titleKey: 'modal.failed_job_retry.title',
        descriptionKey: 'modal.failed_job_retry.description',
        confirmKey: 'modal.failed_job_retry.confirm',
        tone: 'warning',
        subject: job.uuid,
        affectedCount: 1,
    });

    if (!confirmed) {
        return;
    }

    submitRetry([job.uuid], null);
}

async function retrySelected(): Promise<void> {
    if (selected.value.length === 0) {
        return;
    }

    const confirmed = await modal.confirm({
        titleKey: 'modal.failed_jobs_retry.title',
        descriptionKey: 'modal.failed_jobs_retry.description',
        confirmKey: 'modal.failed_jobs_retry.confirm',
        tone: 'danger',
        affectedCount: selected.value.length,
        typedConfirmation: 'RETRY',
    });

    if (!confirmed) {
        return;
    }

    submitRetry(selected.value, 'RETRY');
}

function submitRetry(uuids: string[], confirmation: string | null): void {
    retrying.value = true;
    router.post(
        '/admin/queues/failed-jobs/retry',
        { uuids, confirmation },
        {
            preserveScroll: true,
            onFinish: () => {
                retrying.value = false;
            },
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}

function isWithinDateRange(job: FailedJob): boolean {
    const timestamp = Date.parse(job.failedAt.includes('T') ? job.failedAt : job.failedAt.replace(' ', 'T'));

    if (Number.isNaN(timestamp)) {
        return dateFrom.value === '' && dateTo.value === '';
    }

    if (dateFrom.value !== '' && timestamp < Date.parse(`${dateFrom.value}T00:00:00`)) {
        return false;
    }

    if (dateTo.value !== '' && timestamp > Date.parse(`${dateTo.value}T23:59:59.999`)) {
        return false;
    }

    return true;
}

function detailLines(value: string): { key: string; text: string; kind: 'frame' | 'plain' }[] {
    return value.split(/\r?\n/).map((line, index) => ({
        key: `${index}-${line}`,
        text: line,
        kind: line.startsWith('#') ? 'frame' : 'plain',
    }));
}
</script>

<template>
    <Head :title="t('pages.admin.queues.head_title')" />
    <AdminLayout :title="t('pages.admin.queues.title')" :title-icon="IconRotateClockwise">
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
                :summary="`Showing ${filteredJobs.length} of ${props.summary.visibleCount} loaded failed jobs.`"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div
                    class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(9rem,0.6fr)_minmax(9rem,0.6fr)]"
                >
                    <FormInput v-model="draftSearch" label="Search" placeholder="UUID, job, exception, queue" />
                    <FormSelect v-model="draftConnection" label="Connection" :options="connections" />
                    <FormSelect v-model="draftQueue" label="Queue" :options="queues" />
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

            <section class="rounded-lg border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <FormCheckbox
                        :model-value="allVisibleSelected"
                        :indeterminate="partiallySelected"
                        aria-label="Select all visible failed jobs"
                        @click="toggleAllVisible"
                    >
                        Select all visible
                    </FormCheckbox>
                    <FormButton
                        tone="danger"
                        :icon="IconRotateClockwise"
                        :disabled="selected.length === 0"
                        :loading="retrying"
                        @click="retrySelected"
                    >
                        Retry selected
                    </FormButton>
                </div>
            </section>

            <section class="space-y-3">
                <article
                    v-for="job in filteredJobs"
                    :key="job.uuid"
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-3 px-4 py-3">
                        <FormCheckbox v-model="selected" :value="job.uuid" :aria-label="`Select failed job ${job.uuid}`" class="mt-1" />
                        <button type="button" class="min-w-0 text-left" :aria-expanded="expanded === job.uuid" @click="toggle(job.uuid)">
                            <span class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex h-6 items-center rounded-md border border-rose-200 bg-rose-50 px-2 text-xs font-semibold uppercase text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200"
                                >
                                    failed
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ job.failedAt }}</span>
                                <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ job.connection }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ job.queue }}</span>
                            </span>
                            <Tooltip :text="job.exceptionMessage" placement="top" align="start" full-width wide>
                                <span class="mt-2 block truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ job.displayName }}
                                </span>
                            </Tooltip>
                            <span class="mt-1 block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ job.exceptionType }}: {{ job.exceptionMessage }}
                            </span>
                        </button>
                        <div class="flex items-center gap-2">
                            <FormButton tone="neutral" :icon="IconRotateClockwise" :disabled="retrying" @click="retryOne(job)">
                                Retry
                            </FormButton>
                            <button
                                type="button"
                                class="rounded-md p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-900 dark:hover:text-zinc-200"
                                :aria-label="expanded === job.uuid ? 'Collapse failed job details' : 'Expand failed job details'"
                                @click="toggle(job.uuid)"
                            >
                                <IconChevronDown
                                    aria-hidden="true"
                                    class="h-5 w-5 transition"
                                    :class="{ 'rotate-180': expanded === job.uuid }"
                                    :stroke-width="1.8"
                                />
                            </button>
                        </div>
                    </div>

                    <div v-if="expanded === job.uuid" class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-800">
                        <dl class="grid gap-3 text-xs sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">UUID</dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ job.uuid }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Job class</dt>
                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">{{ job.jobClass }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Connection</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ job.connection }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Queue</dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ job.queue }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                            <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Payload</p>
                                <pre
                                    class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words font-mono text-xs leading-5 text-zinc-800 dark:text-zinc-100"
                                    >{{ job.payload }}</pre>
                            </section>
                            <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Exception</p>
                                <div class="mt-2 max-h-96 overflow-auto font-mono text-xs leading-5 text-zinc-800 dark:text-zinc-100">
                                    <div
                                        v-for="line in detailLines(job.exception)"
                                        :key="line.key"
                                        class="whitespace-pre-wrap break-words"
                                        :class="{ 'pl-3 text-zinc-600 dark:text-zinc-300': line.kind === 'frame' }"
                                    >
                                        {{ line.text }}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </article>

                <section
                    v-if="filteredJobs.length === 0"
                    class="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400"
                >
                    No failed jobs match the current filters.
                </section>
            </section>
        </section>
    </AdminLayout>
</template>
