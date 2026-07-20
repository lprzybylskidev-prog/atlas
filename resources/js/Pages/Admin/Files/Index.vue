<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconChevronDown, IconFileAlert, IconFiles, IconRotateClockwise } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import FilterPanel from '../../../Components/FilterPanel.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import SeverityBadge from '../../../Components/SeverityBadge.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import { useModal } from '../../../Composables/useModal';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface FileRecord {
    publicId: string;
    originalName: string;
    extension: string;
    mimeType: string;
    sizeBytes: number;
    checksumSha256: string;
    scanState: string;
    scanAttempts: number;
    quarantinedAt: string | null;
    availableAt: string | null;
    provider: string | null;
    engineVersion: string | null;
    signatureVersion: string | null;
    scannedAt: string | null;
    threatName: string | null;
}

interface FileSummary {
    total: number;
    pending: number;
    scanning: number;
    clean: number;
    infected: number;
    failed: number;
    unsupported: number;
}

const props = defineProps<{ files: FileRecord[]; summary: FileSummary }>();
const { t } = useTranslator('en');
const modal = useModal();
const draftSearch = ref('');
const draftState = ref('all');
const search = ref('');
const state = ref('all');
const expanded = ref<string | null>(props.files[0]?.publicId ?? null);
const rescanning = ref<string | null>(null);

const states = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'scanning', label: 'Scanning' },
    { value: 'clean', label: 'Clean' },
    { value: 'infected', label: 'Infected' },
    { value: 'failed', label: 'Failed' },
    { value: 'unsupported', label: 'Unsupported' },
];

const filteredFiles = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.files.filter((file) => {
        if (state.value !== 'all' && file.scanState !== state.value) {
            return false;
        }

        return (
            query === '' ||
            [
                file.publicId,
                file.originalName,
                file.mimeType,
                file.checksumSha256,
                file.scanState,
                file.provider ?? '',
                file.threatName ?? '',
            ]
                .join(' ')
                .toLowerCase()
                .includes(query)
        );
    });
});

const summaryItems = computed<{ label: string; value: string; icon: Component }[]>(() => [
    { label: 'Total', value: String(props.summary.total), icon: IconFiles },
    { label: 'Clean', value: String(props.summary.clean), icon: IconFiles },
    { label: 'Blocked', value: String(props.summary.infected + props.summary.failed + props.summary.unsupported), icon: IconFileAlert },
    { label: 'Queued', value: String(props.summary.pending + props.summary.scanning), icon: IconRotateClockwise },
]);

function applyFilters(): void {
    search.value = draftSearch.value;
    state.value = draftState.value;
}

function clearFilters(): void {
    draftSearch.value = '';
    draftState.value = 'all';
    applyFilters();
}

function toggle(publicId: string): void {
    expanded.value = expanded.value === publicId ? null : publicId;
}

async function rescan(file: FileRecord): Promise<void> {
    const confirmed = await modal.confirm({
        titleKey: 'modal.files_rescan.title',
        descriptionKey: 'modal.files_rescan.description',
        confirmKey: 'modal.files_rescan.confirm',
        tone: file.scanState === 'infected' ? 'danger' : 'warning',
        subject: file.originalName,
        affectedCount: 1,
    });

    if (!confirmed) {
        return;
    }

    rescanning.value = file.publicId;
    router.post(`/admin/files/${file.publicId}/rescan`, {}, { preserveScroll: true, onFinish: () => (rescanning.value = null) });
}

function stateSeverity(value: string): string {
    if (value === 'clean') {
        return 'success';
    }

    if (value === 'infected' || value === 'failed') {
        return 'failed';
    }

    return value === 'pending' || value === 'scanning' || value === 'unsupported' ? 'warning' : 'info';
}

function bytes(value: number): string {
    if (value < 1024) {
        return `${value} B`;
    }

    return value < 1024 * 1024 ? `${(value / 1024).toFixed(1)} KB` : `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function shortChecksum(value: string): string {
    return value.length > 18 ? `${value.slice(0, 12)}...${value.slice(-6)}` : value;
}
</script>

<template>
    <Head :title="t('pages.admin.files.head_title')" />
    <AdminLayout :title="t('pages.admin.files.title')" :title-icon="IconFiles">
        <section class="space-y-5">
            <MetricGrid :items="summaryItems" />

            <FilterPanel
                :summary="`Showing ${filteredFiles.length} of ${props.files.length} loaded files.`"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px]">
                    <FormInput v-model="draftSearch" name="search" label="Search" type="text" autocomplete="off" />
                    <FormSelect v-model="draftState" name="state" label="Scan state" :options="states" />
                </div>
            </FilterPanel>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                            <tr>
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3">File</th>
                                <th class="px-4 py-3">State</th>
                                <th class="px-4 py-3">Size</th>
                                <th class="px-4 py-3">Checksum</th>
                                <th class="px-4 py-3">Scanned</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                            <template v-for="file in filteredFiles" :key="file.publicId">
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <button
                                            type="button"
                                            class="rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus:ring-2 focus:ring-teal-500 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                                            @click="toggle(file.publicId)"
                                        >
                                            <IconChevronDown
                                                aria-hidden="true"
                                                class="h-4 w-4 transition-transform"
                                                :class="{ 'rotate-180': expanded === file.publicId }"
                                                :stroke-width="1.8"
                                            />
                                        </button>
                                    </td>
                                    <td class="max-w-[22rem] px-4 py-3">
                                        <p class="truncate font-medium text-zinc-950 dark:text-zinc-50">{{ file.originalName }}</p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ file.mimeType }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <SeverityBadge :value="stateSeverity(file.scanState)" :label="file.scanState" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        {{ bytes(file.sizeBytes) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ shortChecksum(file.checksumSha256) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                        {{ file.scannedAt ?? 'Not scanned' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Tooltip text="Queue malware rescan">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-teal-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                                :disabled="rescanning === file.publicId"
                                                @click="rescan(file)"
                                            >
                                                <IconRotateClockwise aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                            </button>
                                        </Tooltip>
                                    </td>
                                </tr>
                                <tr v-if="expanded === file.publicId">
                                    <td></td>
                                    <td colspan="6" class="bg-zinc-50 px-4 py-4 dark:bg-zinc-900/60">
                                        <dl class="grid gap-3 text-xs sm:grid-cols-2 xl:grid-cols-4">
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Public ID</dt>
                                                <dd class="mt-1 break-all font-mono text-zinc-800 dark:text-zinc-200">
                                                    {{ file.publicId }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Provider</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.provider ?? 'None' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Engine</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.engineVersion ?? 'Unknown' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Signatures</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">
                                                    {{ file.signatureVersion ?? 'Unknown' }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Attempts</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.scanAttempts }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Quarantined</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.quarantinedAt ?? 'Unknown' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Available</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.availableAt ?? 'Blocked' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold uppercase text-zinc-500 dark:text-zinc-400">Threat</dt>
                                                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ file.threatName ?? 'None' }}</dd>
                                            </div>
                                        </dl>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="filteredFiles.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No files match the current filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>
