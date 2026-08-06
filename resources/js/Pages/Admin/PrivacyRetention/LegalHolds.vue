<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconCalendarTime, IconListDetails, IconLock, IconPlus, IconScale, IconShieldCheck } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { usePrivacyRetentionSubnavigation } from '../../../Composables/usePrivacyRetentionSubnavigation';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';

interface PrivacyLegalHoldRow extends Record<string, unknown> {
    publicId: string;
    subjectType: string;
    subjectIdentifier: string;
    status: string;
    teamPublicId: string;
    teamName: string;
    createdByPublicId: string;
    reason: string;
    expiresOn: string;
    releasedAt: string;
    releaseReason: string;
    createdAt: string;
}

interface PrivacyLegalHoldSummary {
    total: number;
    visible: number;
    active: number;
    expired: number;
    released: number;
    withExpiry: number;
}

const props = defineProps<{
    holds: PrivacyLegalHoldRow[];
    summary: PrivacyLegalHoldSummary;
    filterOptions: {
        subjectTypes: string[];
        teams: string[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const subnavigation = usePrivacyRetentionSubnavigation('/admin/privacy-retention/legal-holds', t);
const filterKeys = ['status', 'subject_type', 'team'];
const filterDefaults = {
    status: 'all',
    subject_type: 'all',
    team: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const tableFilters = computed(() => filterValues());

const columns = computed<DataTableColumn<PrivacyLegalHoldRow>[]>(() => [
    { key: 'subjectType', label: t('pages.admin.privacy_retention.legal_holds.table.subject_type'), format: 'status' },
    { key: 'subjectIdentifier', label: t('pages.admin.privacy_retention.legal_holds.table.subject_identifier') },
    { key: 'status', label: t('pages.admin.privacy_retention.legal_holds.table.status'), format: 'status-badge' },
    { key: 'teamName', label: t('pages.admin.privacy_retention.legal_holds.table.team') },
    { key: 'reason', label: t('pages.admin.privacy_retention.legal_holds.table.reason') },
    { key: 'expiresOn', label: t('pages.admin.privacy_retention.legal_holds.table.expires_on'), format: 'date' },
    { key: 'createdAt', label: t('pages.admin.privacy_retention.legal_holds.table.created_at'), format: 'datetime' },
    { key: 'createdByPublicId', label: t('pages.admin.privacy_retention.legal_holds.table.created_by'), hidden: true },
    { key: 'releasedAt', label: t('pages.admin.privacy_retention.legal_holds.table.released_at'), format: 'datetime', hidden: true },
    { key: 'releaseReason', label: t('pages.admin.privacy_retention.legal_holds.table.release_reason'), hidden: true },
    { key: 'publicId', label: t('pages.admin.privacy_retention.legal_holds.table.public_id'), hidden: true },
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.legal_holds.filters.any_status') },
    { value: 'active', label: statusLabel('active') },
    { value: 'expired', label: statusLabel('expired') },
    { value: 'released', label: statusLabel('released') },
]);
const subjectTypeOptions = computed<FormSelectOption[]>(() => [
    ...optionsWithAll(
        props.filterOptions.subjectTypes,
        t('pages.admin.privacy_retention.legal_holds.filters.any_subject_type'),
        subjectTypeLabel,
    ),
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    ...optionsWithAll(props.filterOptions.teams, t('pages.admin.privacy_retention.legal_holds.filters.any_team'), teamLabel),
]);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        status: String(props.table.state.filters?.status ?? 'all'),
        subject_type: String(props.table.state.filters?.subject_type ?? 'all'),
        team: String(props.table.state.filters?.team ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function statusLabel(value: string): string {
    const key = `pages.admin.privacy_retention.legal_holds.status.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function subjectTypeLabel(value: string): string {
    const key = `pages.admin.privacy_retention.subject_type.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function teamLabel(value: string): string {
    return props.holds.find((hold) => hold.teamPublicId === value)?.teamName || value;
}
</script>

<template>
    <Head :title="t('pages.admin.privacy_retention.legal_holds.head_title')" />
    <AppLayout
        mode="admin"
        :title="t('pages.admin.privacy_retention.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.privacy_retention.nav.label')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.total')"
                    :value="summary.total"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.visible')"
                    :value="summary.visible"
                    :icon="IconScale"
                    tone="zinc"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.active')"
                    :value="summary.active"
                    :icon="IconLock"
                    tone="rose"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.expired')"
                    :value="summary.expired"
                    :icon="IconCalendarTime"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.released')"
                    :value="summary.released"
                    :icon="IconShieldCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.legal_holds.metric.with_expiry')"
                    :value="summary.withExpiry"
                    :icon="IconCalendarTime"
                    tone="teal"
                />
            </div>

            <div class="flex justify-end">
                <ActionLink href="/admin/privacy-retention/legal-holds/create" :icon="IconPlus" tone="primary">
                    {{ t('pages.admin.privacy_retention.legal_holds.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.privacy_retention.legal_holds.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-3">
                    <FormSelect
                        v-model="filters.status"
                        :label="t('pages.admin.privacy_retention.legal_holds.filters.status')"
                        :options="statusOptions"
                    />
                    <FormSelect
                        v-model="filters.subject_type"
                        :label="t('pages.admin.privacy_retention.legal_holds.filters.subject_type')"
                        :options="subjectTypeOptions"
                    />
                    <FormSelect
                        v-model="filters.team"
                        :label="t('pages.admin.privacy_retention.legal_holds.filters.team')"
                        :options="teamOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.privacy_retention.legal_holds.table.title')"
                :rows="holds.map((row) => ({ ...row, subjectType: subjectTypeLabel(row.subjectType) }))"
                :columns="columns"
                row-key="publicId"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.privacy_retention.legal_holds.table.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
