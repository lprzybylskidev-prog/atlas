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
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';

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

const props = defineProps<{ files: FileRecord[]; summary: FileSummary; exports: DataTableExportMeta }>();
const { t } = useTranslator();
const draftSearch = ref('');
const draftState = ref('all');
const search = ref('');
const state = ref('all');

const states = computed(() => [
    { value: 'all', label: t('pages.admin.files.all') },
    { value: 'pending', label: t('pages.admin.files.state.pending') },
    { value: 'scanning', label: t('pages.admin.files.state.scanning') },
    { value: 'clean', label: t('pages.admin.files.state.clean') },
    { value: 'infected', label: t('pages.admin.files.state.infected') },
    { value: 'failed', label: t('pages.admin.files.state.failed') },
    { value: 'unsupported', label: t('pages.admin.files.state.unsupported') },
]);

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
    { label: t('pages.admin.files.metric.total'), value: String(props.summary.total), icon: IconFiles },
    { label: t('pages.admin.files.metric.clean'), value: String(props.summary.clean), icon: IconFiles },
    {
        label: t('pages.admin.files.metric.blocked'),
        value: String(props.summary.infected + props.summary.failed + props.summary.unsupported),
        icon: IconFileAlert,
    },
    {
        label: t('pages.admin.files.metric.queued'),
        value: String(props.summary.pending + props.summary.scanning),
        icon: IconRotateClockwise,
    },
]);

const columns: DataTableColumn<FileRecord>[] = [
    { key: 'publicId', label: t('pages.admin.files.public_id'), hidden: true },
    { key: 'originalName', label: t('pages.admin.files.file') },
    { key: 'mimeType', label: t('pages.admin.files.mime_type') },
    { key: 'scanState', label: t('pages.admin.files.state'), format: 'severity' },
    { key: 'sizeBytes', label: t('pages.admin.files.size'), format: 'file-size' },
    { key: 'checksumSha256', label: t('pages.admin.files.checksum') },
    { key: 'scannedAt', label: t('pages.admin.files.scanned'), format: 'datetime' },
    { key: 'provider', label: t('pages.admin.files.provider'), hidden: true },
    { key: 'engineVersion', label: t('pages.admin.files.engine'), hidden: true },
    { key: 'signatureVersion', label: t('pages.admin.files.signatures'), hidden: true },
    { key: 'scanAttempts', label: t('pages.admin.files.attempts'), format: 'number', hidden: true },
    { key: 'quarantinedAt', label: t('pages.admin.files.quarantined'), format: 'datetime', hidden: true },
    { key: 'availableAt', label: t('pages.admin.files.available'), format: 'datetime', hidden: true },
    { key: 'threatName', label: t('pages.admin.files.threat'), hidden: true },
];

const actions: DataTableAction<FileRecord>[] = [
    {
        key: 'rescan',
        label: t('pages.admin.files.queue_rescan'),
        method: 'post',
        href: (file) => `/admin/files/${file.publicId}/rescan`,
        confirm: (file) => t('pages.admin.files.queue_rescan_confirm', { file: file.originalName }),
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
            <NoticeBanner :title="t('pages.admin.files.bounded_title')">
                {{ t('pages.admin.files.bounded') }}
            </NoticeBanner>

            <FilterPanel
                :summary="t('pages.admin.files.loaded_summary', { visible: filteredFiles.length, loaded: props.files.length })"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px]">
                    <FormInput v-model="draftSearch" name="search" :label="t('pages.admin.files.search')" type="text" autocomplete="off" />
                    <FormSelect v-model="draftState" name="state" :label="t('pages.admin.files.scan_state')" :options="states" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.files.title')"
                :rows="filteredFiles"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                state-key="admin.files"
                export-key="admin.files"
                :exports="exports"
                :filters="{ state }"
                :empty-label="t('pages.admin.files.empty_filtered')"
            />
        </PageStack>
    </AdminLayout>
</template>
