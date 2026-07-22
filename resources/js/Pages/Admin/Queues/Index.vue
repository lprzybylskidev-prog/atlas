<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconChevronDown, IconDatabase, IconListDetails, IconRotateClockwise, IconServer } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import CodeViewer from '../../../Components/CodeViewer.vue';
import DataTableExportMenu from '../../../Components/DataTableExportMenu.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TextBadge from '../../../Components/TextBadge.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import UiState from '../../../Components/UiState.vue';
import { useModal } from '../../../Composables/useModal';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableExportMeta } from '../../../Types/data-table';

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
    exports: DataTableExportMeta;
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
const failedJobExportColumns = [
    'uuid',
    'connection',
    'queue',
    'failedAt',
    'displayName',
    'jobClass',
    'exceptionType',
    'exceptionMessage',
] as const;

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
</script>

<template>
    <Head :title="t('pages.admin.queues.head_title')" />
    <AdminLayout :title="t('pages.admin.queues.title')" :title-icon="IconRotateClockwise">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <FilterPanel
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
                    <FormDateInput v-model="draftDateFrom" label="From date" />
                    <FormDateInput v-model="draftDateTo" label="To date" />
                </div>
            </FilterPanel>

            <SurfaceCard title="Bulk retry" :icon="IconRotateClockwise">
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
            </SurfaceCard>

            <section class="space-y-3">
                <div class="flex justify-end">
                    <DataTableExportMenu
                        table-key="admin.queues.failed-jobs"
                        :exports="exports"
                        :columns="[...failedJobExportColumns]"
                        :column-order="[...failedJobExportColumns]"
                        :filters="{ connection, queue, from: dateFrom, to: dateTo }"
                        :search="search"
                        sort="failedAt"
                        direction="desc"
                    />
                </div>

                <SurfaceCard
                    v-for="job in filteredJobs"
                    :key="job.uuid"
                    :aria-label="`Failed job ${job.uuid}`"
                    :padded="false"
                    overflow="hidden"
                >
                    <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-3 px-4 py-3">
                        <FormCheckbox v-model="selected" :value="job.uuid" :aria-label="`Select failed job ${job.uuid}`" class="mt-1" />
                        <button type="button" class="min-w-0 text-left" :aria-expanded="expanded === job.uuid" @click="toggle(job.uuid)">
                            <span class="flex flex-wrap items-center gap-2">
                                <TextBadge label="failed" tone="danger" uppercase />
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
                            <CodeViewer title="Payload" :content="job.payload" language="json" />
                            <CodeViewer title="Exception" :content="job.exception" language="stack" />
                        </div>
                    </div>
                </SurfaceCard>

                <UiState v-if="filteredJobs.length === 0" variant="no-results" title="No failed jobs match the current filters." />
            </section>
        </PageStack>
    </AdminLayout>
</template>
