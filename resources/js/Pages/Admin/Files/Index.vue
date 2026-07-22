<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconFileAlert, IconFiles, IconRotateClockwise } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn } from '../../../Types/data-table';

interface FileRecord extends Record<string, unknown> {
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
const draftSearch = ref('');
const draftState = ref('all');
const search = ref('');
const state = ref('all');

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

const columns: DataTableColumn<FileRecord>[] = [
    { key: 'publicId', label: 'Public ID', hidden: true },
    { key: 'originalName', label: 'File' },
    { key: 'mimeType', label: 'MIME type' },
    { key: 'scanState', label: 'State', format: 'severity' },
    { key: 'sizeBytes', label: 'Size', format: 'file-size' },
    { key: 'checksumSha256', label: 'Checksum' },
    { key: 'scannedAt', label: 'Scanned', format: 'datetime' },
    { key: 'provider', label: 'Provider', hidden: true },
    { key: 'engineVersion', label: 'Engine', hidden: true },
    { key: 'signatureVersion', label: 'Signatures', hidden: true },
    { key: 'scanAttempts', label: 'Attempts', format: 'number', hidden: true },
    { key: 'quarantinedAt', label: 'Quarantined', format: 'datetime', hidden: true },
    { key: 'availableAt', label: 'Available', format: 'datetime', hidden: true },
    { key: 'threatName', label: 'Threat', hidden: true },
];

const actions: DataTableAction<FileRecord>[] = [
    {
        key: 'rescan',
        label: 'Queue malware rescan',
        method: 'post',
        href: (file) => `/admin/files/${file.publicId}/rescan`,
        confirm: (file) => `Queue malware rescan for ${file.originalName}?`,
        tone: 'warning',
    },
];

function applyFilters(): void {
    search.value = draftSearch.value;
    state.value = draftState.value;
}

function clearFilters(): void {
    draftSearch.value = '';
    draftState.value = 'all';
    applyFilters();
}
</script>

<template>
    <Head :title="t('pages.admin.files.head_title')" />
    <AdminLayout :title="t('pages.admin.files.title')" :title-icon="IconFiles">
        <PageStack>
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

            <DataTable
                title="Files"
                :rows="filteredFiles"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                state-key="admin.files"
                empty-label="No files match the current filters."
            />
        </PageStack>
    </AdminLayout>
</template>
