<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconCircleCheck,
    IconClipboardCheck,
    IconFiles,
    IconProgress,
    IconShieldCheck,
    IconShieldSearch,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TruncatedText from '../../../Components/TruncatedText.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface FileRow extends Record<string, unknown> {
    publicId: string;
    originalName: string;
    extension: string | null;
    mimeType: string | null;
    sizeBytes: number;
    checksumSha256: string | null;
    scanState: string | null;
    handlingStatus: string;
    canAcknowledge: boolean;
    acknowledgedAt: string | null;
    acknowledgedBy: string | null;
    acknowledgementReason: string | null;
    scanAttempts: number;
    quarantinedAt: string | null;
    availableAt: string | null;
    createdAt: string | null;
    provider: string | null;
    engineVersion: string | null;
    signatureVersion: string | null;
    scannedAt: string | null;
    result: string | null;
    resultLabel?: string;
    threatName: string | null;
}

interface FilesSummary {
    total: number;
    pending: number;
    scanning: number;
    clean: number;
    infected: number;
    failed: number;
    unsupported: number;
    blocked: number;
    handled: number;
    queued: number;
    visible: number;
}

const props = defineProps<{
    files: FileRow[];
    scanEvidence: FileRow[];
    summary: FilesSummary;
    filterOptions: {
        extensions: string[];
        providers: string[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['state', 'extension', 'provider', 'availability', 'handling', 'from', 'to'];
const filterDefaults = {
    state: 'all',
    extension: 'all',
    provider: 'all',
    availability: 'all',
    handling: 'needs_attention',
    from: '',
    to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<FileRow[]>(() =>
    props.files.map((file) => ({
        ...file,
        scanState: scanStateLabel(file.scanState),
        handlingStatus: handlingStatusLabel(file.handlingStatus),
        resultLabel: scanStateLabel(file.result),
    })),
);
const evidenceRows = computed<FileRow[]>(() =>
    props.scanEvidence.map((file) => ({
        ...file,
        scanState: scanStateLabel(file.scanState),
        handlingStatus: handlingStatusLabel(file.handlingStatus),
        resultLabel: scanStateLabel(file.result),
    })),
);
const columns = computed<DataTableColumn<FileRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.files.table.public_id'), hidden: true },
    { key: 'originalName', label: t('pages.admin.files.table.file') },
    { key: 'extension', label: t('pages.admin.files.table.extension') },
    { key: 'mimeType', label: t('pages.admin.files.table.mime_type') },
    { key: 'scanState', label: t('pages.admin.files.table.scan_state'), format: 'status' },
    { key: 'handlingStatus', label: t('pages.admin.files.table.handling_status'), format: 'status' },
    { key: 'sizeBytes', label: t('pages.admin.files.table.size'), format: 'file-size' },
    { key: 'checksumSha256', label: t('pages.admin.files.table.checksum') },
    { key: 'scannedAt', label: t('pages.admin.files.table.scanned_at'), format: 'datetime' },
    { key: 'acknowledgedAt', label: t('pages.admin.files.table.handled_at'), format: 'datetime', hidden: true },
    { key: 'acknowledgedBy', label: t('pages.admin.files.table.handled_by'), hidden: true },
    { key: 'acknowledgementReason', label: t('pages.admin.files.table.handling_reason'), hidden: true },
    { key: 'provider', label: t('pages.admin.files.table.provider'), hidden: true },
    { key: 'engineVersion', label: t('pages.admin.files.table.engine'), hidden: true },
    { key: 'signatureVersion', label: t('pages.admin.files.table.signatures'), hidden: true },
    { key: 'scanAttempts', label: t('pages.admin.files.table.attempts'), format: 'number', hidden: true },
    { key: 'quarantinedAt', label: t('pages.admin.files.table.quarantined_at'), format: 'datetime', hidden: true },
    { key: 'availableAt', label: t('pages.admin.files.table.available_at'), format: 'datetime', hidden: true },
    { key: 'threatName', label: t('pages.admin.files.table.threat'), hidden: true },
    { key: 'createdAt', label: t('pages.admin.files.table.created_at'), format: 'datetime', hidden: true },
]);
const actions = computed<DataTableAction<FileRow>[]>(() => [
    {
        key: 'rescan',
        label: t('pages.admin.files.actions.rescan'),
        method: 'post',
        href: (file) => `/admin/files/${encodeURIComponent(file.publicId)}/rescan`,
        confirm: (file) => t('pages.admin.files.actions.rescan_confirm', { file: file.originalName }),
        tone: 'warning',
    },
    {
        key: 'acknowledge',
        label: t('pages.admin.files.actions.acknowledge'),
        method: 'post',
        href: (file) => `/admin/files/acknowledge?files[]=${encodeURIComponent(file.publicId)}`,
        confirm: (file) => t('pages.admin.files.actions.acknowledge_confirm', { file: file.originalName }),
        tone: 'success',
        visible: (file) => file.canAcknowledge,
    },
]);
const bulkActions = computed<DataTableBulkAction[]>(() => [
    { key: 'acknowledge', label: t('pages.admin.files.actions.acknowledge_selected'), tone: 'success' },
]);
const stateOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.files.filters.any_state') },
    ...['pending', 'scanning', 'clean', 'infected', 'failed', 'unsupported'].map((state) => ({
        value: state,
        label: scanStateLabel(state),
    })),
]);
const extensionOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.extensions, t('pages.admin.files.filters.any_extension')),
);
const providerOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.providers, t('pages.admin.files.filters.any_provider')),
);
const availabilityOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.files.filters.any_availability') },
    { value: 'available', label: t('pages.admin.files.filters.available') },
    { value: 'blocked', label: t('pages.admin.files.filters.blocked') },
]);
const handlingOptions = computed<FormSelectOption[]>(() => [
    { value: 'needs_attention', label: t('pages.admin.files.filters.needs_attention') },
    { value: 'handled', label: t('pages.admin.files.filters.handled') },
    { value: 'all', label: t('pages.admin.files.filters.any_handling') },
]);
const tableFilters = computed(() => filterValues());
const hasBlockedFiles = computed(() => props.summary.blocked > 0);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        state: String(props.table.state.filters?.state ?? 'all'),
        extension: String(props.table.state.filters?.extension ?? 'all'),
        provider: String(props.table.state.filters?.provider ?? 'all'),
        availability: String(props.table.state.filters?.availability ?? 'all'),
        handling: String(props.table.state.filters?.handling ?? 'needs_attention'),
        from: String(props.table.state.filters?.from ?? ''),
        to: String(props.table.state.filters?.to ?? ''),
    };
}

function allOptions(values: string[], label: string): FormSelectOption[] {
    return [
        { value: 'all', label },
        ...values.map((value) => ({
            value,
            label: value,
        })),
    ];
}

function scanStateLabel(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const keys: Record<string, string> = {
        clean: 'pages.admin.files.states.clean',
        failed: 'pages.admin.files.states.failed',
        infected: 'pages.admin.files.states.infected',
        pending: 'pages.admin.files.states.pending',
        scanning: 'pages.admin.files.states.scanning',
        unsupported: 'pages.admin.files.states.unsupported',
    };

    return keys[value] === undefined ? value : t(keys[value]);
}

function handlingStatusLabel(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const keys: Record<string, string> = {
        handled: 'pages.admin.files.handling.handled',
        needs_attention: 'pages.admin.files.handling.needs_attention',
        not_applicable: 'pages.admin.files.handling.not_applicable',
    };

    return keys[value] === undefined ? value : t(keys[value]);
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    if (payload.action.key !== 'acknowledge') {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        router.post(
            '/admin/files/acknowledge',
            { files: payload.rowIds },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => reject(),
            },
        );
    });
}
</script>

<template>
    <Head :title="t('pages.admin.files.head_title')" />
    <AdminLayout :title="t('pages.admin.files.title')" :title-icon="IconFiles">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile :label="t('pages.admin.files.metric.total')" :value="summary.total" :icon="IconFiles" tone="sky" />
                <OperationalMetricTile
                    :label="t('pages.admin.files.metric.queued')"
                    :value="summary.queued"
                    :icon="IconProgress"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.files.metric.clean')"
                    :value="summary.clean"
                    :icon="IconCircleCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.files.metric.blocked')"
                    :value="summary.blocked"
                    :icon="IconAlertTriangle"
                    :tone="hasBlockedFiles ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.files.metric.handled')"
                    :value="summary.handled"
                    :icon="IconClipboardCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.files.metric.visible')"
                    :value="summary.visible"
                    :icon="IconShieldSearch"
                    tone="teal"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.files.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.state" :label="t('pages.admin.files.filters.state')" :options="stateOptions" />
                    <FormSelect v-model="filters.extension" :label="t('pages.admin.files.filters.extension')" :options="extensionOptions" />
                    <FormSelect v-model="filters.provider" :label="t('pages.admin.files.filters.provider')" :options="providerOptions" />
                    <FormSelect
                        v-model="filters.availability"
                        :label="t('pages.admin.files.filters.availability')"
                        :options="availabilityOptions"
                    />
                    <FormSelect v-model="filters.handling" :label="t('pages.admin.files.filters.handling')" :options="handlingOptions" />
                    <FormDateInput v-model="filters.from" :label="t('pages.admin.files.filters.created_from')" :ui-locale="locale" />
                    <FormDateInput v-model="filters.to" :label="t('pages.admin.files.filters.created_to')" :ui-locale="locale" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.files.table.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.files.table.empty')"
            />

            <SurfaceCard :title="t('pages.admin.files.evidence.title')" :icon="IconShieldCheck" tone="zinc">
                <div v-if="evidenceRows.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('pages.admin.files.table.empty') }}
                </div>
                <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <details v-for="file in evidenceRows" :key="file.publicId" class="group py-3">
                        <summary
                            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 text-sm font-medium text-zinc-950 dark:text-zinc-50"
                        >
                            <span class="min-w-0 truncate">{{ file.originalName }} · {{ file.scanState }}</span>
                            <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ file.publicId }}</span>
                        </summary>
                        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.provider') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.provider ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.engine') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.engineVersion ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.signatures') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.signatureVersion ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.result') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.resultLabel || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.threat') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.threatName ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.attempts') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">{{ file.scanAttempts }}</dd>
                            </div>
                            <div class="md:col-span-2 xl:col-span-3">
                                <dt class="text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ t('pages.admin.files.table.checksum') }}
                                </dt>
                                <dd class="mt-1 text-zinc-800 dark:text-zinc-100">
                                    <TruncatedText :text="file.checksumSha256 ?? '-'" />
                                </dd>
                            </div>
                        </dl>
                    </details>
                </div>
            </SurfaceCard>
        </PageStack>
    </AdminLayout>
</template>
