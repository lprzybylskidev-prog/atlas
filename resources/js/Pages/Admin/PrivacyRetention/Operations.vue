<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCheck, IconFingerprint, IconHistory, IconLock, IconShieldCheck } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { usePrivacyRetentionSubnavigation } from '../../../Composables/usePrivacyRetentionSubnavigation';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface PrivacyOperationRow extends Record<string, unknown> {
    publicId: string;
    operation: string;
    status: string;
    subjectType: string;
    subjectIdentifier: string;
    dryRun: boolean;
    canExecute: boolean;
    estimatedRecords: number;
    participantCount: number;
    blockerCount: number;
    teamPublicId: string;
    actorPublicId: string;
    reason: string;
    confirmationPhrase: string;
    previewedAt: string;
    createdAt: string;
}

interface PrivacyOperationSummary {
    total: number;
    visible: number;
    blocked: number;
    previewed: number;
    hardDelete: number;
    anonymization: number;
}

const props = defineProps<{
    operations: PrivacyOperationRow[];
    summary: PrivacyOperationSummary;
    filterOptions: {
        operations: string[];
        statuses: string[];
        subjectTypes: string[];
        teams: string[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const subnavigation = usePrivacyRetentionSubnavigation('/admin/privacy-retention/operations', t);
const filterKeys = ['operation', 'status', 'subject_type', 'team', 'executable'];
const filterDefaults = {
    operation: 'all',
    status: 'all',
    subject_type: 'all',
    team: 'all',
    executable: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const tableFilters = computed(() => filterValues());
const columns = computed<DataTableColumn<PrivacyOperationRow>[]>(() => [
    { key: 'operation', label: t('pages.admin.privacy_retention.operations.table.operation'), format: 'status' },
    { key: 'status', label: t('pages.admin.privacy_retention.operations.table.status'), format: 'status' },
    { key: 'subjectType', label: t('pages.admin.privacy_retention.operations.table.subject_type'), format: 'status' },
    { key: 'subjectIdentifier', label: t('pages.admin.privacy_retention.operations.table.subject_identifier') },
    { key: 'dryRun', label: t('pages.admin.privacy_retention.operations.table.dry_run'), format: 'boolean' },
    { key: 'canExecute', label: t('pages.admin.privacy_retention.operations.table.can_execute'), format: 'boolean' },
    { key: 'estimatedRecords', label: t('pages.admin.privacy_retention.operations.table.estimated_records'), format: 'number' },
    { key: 'blockerCount', label: t('pages.admin.privacy_retention.operations.table.blockers'), format: 'number' },
    { key: 'previewedAt', label: t('pages.admin.privacy_retention.operations.table.previewed_at'), format: 'datetime' },
    { key: 'teamPublicId', label: t('pages.admin.privacy_retention.operations.table.team') },
    { key: 'participantCount', label: t('pages.admin.privacy_retention.operations.table.participants'), format: 'number', hidden: true },
    { key: 'actorPublicId', label: t('pages.admin.privacy_retention.operations.table.actor'), hidden: true },
    { key: 'reason', label: t('pages.admin.privacy_retention.operations.table.reason'), hidden: true },
    { key: 'confirmationPhrase', label: t('pages.admin.privacy_retention.operations.table.confirmation'), hidden: true },
    { key: 'createdAt', label: t('pages.admin.privacy_retention.operations.table.created_at'), format: 'datetime', hidden: true },
    { key: 'publicId', label: t('pages.admin.privacy_retention.operations.table.public_id'), hidden: true },
]);
const operationOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.operations.filters.any_operation') },
    ...props.filterOptions.operations.map((value) => ({ value, label: operationLabel(value) })),
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.operations.filters.any_status') },
    ...props.filterOptions.statuses.map((value) => ({ value, label: statusLabel(value) })),
]);
const subjectTypeOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.operations.filters.any_subject_type') },
    ...props.filterOptions.subjectTypes.map((value) => ({ value, label: value })),
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.operations.filters.any_team') },
    ...props.filterOptions.teams.map((value) => ({ value, label: value })),
]);
const executableOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.operations.filters.any_executable') },
    { value: 'yes', label: t('datatable.boolean.yes') },
    { value: 'no', label: t('datatable.boolean.no') },
]);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        operation: String(props.table.state.filters?.operation ?? 'all'),
        status: String(props.table.state.filters?.status ?? 'all'),
        subject_type: String(props.table.state.filters?.subject_type ?? 'all'),
        team: String(props.table.state.filters?.team ?? 'all'),
        executable: String(props.table.state.filters?.executable ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function operationLabel(value: string): string {
    return t(`pages.admin.privacy_retention.operation.${value}`);
}

function statusLabel(value: string): string {
    return t(`pages.admin.privacy_retention.preview.status.${value}`);
}
</script>

<template>
    <Head :title="t('pages.admin.privacy_retention.operations.head_title')" />
    <AdminLayout
        :title="t('pages.admin.privacy_retention.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.privacy_retention.nav.label')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.total')"
                    :value="summary.total"
                    :icon="IconHistory"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.visible')"
                    :value="summary.visible"
                    :icon="IconFingerprint"
                    tone="zinc"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.blocked')"
                    :value="summary.blocked"
                    :icon="IconLock"
                    tone="rose"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.previewed')"
                    :value="summary.previewed"
                    :icon="IconCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.hard_delete')"
                    :value="summary.hardDelete"
                    :icon="IconAlertTriangle"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.operations.metric.anonymization')"
                    :value="summary.anonymization"
                    :icon="IconShieldCheck"
                    tone="teal"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.privacy_retention.operations.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect
                        v-model="filters.operation"
                        :label="t('pages.admin.privacy_retention.operations.filters.operation')"
                        :options="operationOptions"
                    />
                    <FormSelect
                        v-model="filters.status"
                        :label="t('pages.admin.privacy_retention.operations.filters.status')"
                        :options="statusOptions"
                    />
                    <FormSelect
                        v-model="filters.subject_type"
                        :label="t('pages.admin.privacy_retention.operations.filters.subject_type')"
                        :options="subjectTypeOptions"
                    />
                    <FormSelect
                        v-model="filters.team"
                        :label="t('pages.admin.privacy_retention.operations.filters.team')"
                        :options="teamOptions"
                    />
                    <FormSelect
                        v-model="filters.executable"
                        :label="t('pages.admin.privacy_retention.operations.filters.executable')"
                        :options="executableOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.privacy_retention.operations.table.title')"
                :rows="operations.map((row) => ({ ...row, operation: operationLabel(row.operation), status: statusLabel(row.status) }))"
                :columns="columns"
                row-key="publicId"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.privacy_retention.operations.table.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
