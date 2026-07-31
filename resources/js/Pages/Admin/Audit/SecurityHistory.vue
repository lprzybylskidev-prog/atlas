<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconFingerprint, IconHistory, IconListDetails, IconShieldCheck, IconUserScan } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ShellSubnavigationItem } from '../../../Types/navigation';

interface FilterOption {
    value: string;
    label: string;
}

interface SecurityEventRow extends Record<string, unknown> {
    publicId: string;
    userName: string;
    userEmail: string;
    userContext: string;
    occurredAt: string;
    action: string;
    result: string;
    source: string;
    teamPublicId: string;
    impersonationSessionId: string;
    reason: string;
}

interface SecuritySummary {
    visible: number;
    rejected: number;
    failed: number;
    impersonated: number;
    withReason: number;
}

const props = defineProps<{
    events: SecurityEventRow[];
    summary: SecuritySummary;
    table: DataTableMeta;
    filterOptions: {
        users: FilterOption[];
        actions: FilterOption[];
        sources: FilterOption[];
    };
}>();

const { locale, t } = useTranslator();
const filterKeys = ['user', 'action', 'result', 'source', 'date_from', 'date_to'];
const filterDefaults = {
    user: 'all',
    action: 'all',
    result: 'all',
    source: 'all',
    date_from: '',
    date_to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const subnavigation = computed<ShellSubnavigationItem[]>(() => [
    {
        key: 'audit.events',
        label: t('pages.admin.audit.nav.events'),
        href: '/admin/audit',
        icon: IconHistory,
        active: false,
    },
    {
        key: 'audit.security',
        label: t('pages.admin.audit.nav.security_history'),
        href: '/admin/audit/security-history',
        icon: IconShieldCheck,
        active: true,
    },
]);
const rows = computed<SecurityEventRow[]>(() =>
    props.events.map((event) => ({
        ...event,
        userContext: contextLabel(event.userContext),
    })),
);
const columns = computed<DataTableColumn<SecurityEventRow>[]>(() => [
    { key: 'occurredAt', label: t('pages.admin.audit.security.table.occurred_at'), format: 'datetime' },
    { key: 'userName', label: t('pages.admin.audit.security.table.user') },
    { key: 'action', label: t('pages.admin.audit.security.table.action') },
    { key: 'result', label: t('pages.admin.audit.security.table.result'), format: 'status' },
    { key: 'source', label: t('pages.admin.audit.security.table.source'), format: 'status' },
    { key: 'teamPublicId', label: t('pages.admin.audit.security.table.team') },
    { key: 'impersonationSessionId', label: t('pages.admin.audit.security.table.impersonation_session') },
    { key: 'reason', label: t('pages.admin.audit.security.table.reason') },
    { key: 'userEmail', label: t('pages.admin.audit.security.table.user_email'), hidden: true },
    { key: 'userContext', label: t('pages.admin.audit.security.table.user_context'), hidden: true },
    { key: 'publicId', label: t('pages.admin.audit.security.table.public_id'), hidden: true },
]);
const actions = computed<DataTableAction<SecurityEventRow>[]>(() => [
    {
        key: 'open',
        label: t('pages.admin.audit.actions.open_impersonation_session'),
        href: (row) => `/admin/audit/impersonation/${encodeURIComponent(row.impersonationSessionId)}`,
        method: 'get',
        visible: (row) => row.impersonationSessionId !== '',
        tone: 'info',
    },
]);
const userOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.users, t('pages.admin.audit.security.filters.any_user')),
);
const actionOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.actions, t('pages.admin.audit.security.filters.any_action')),
);
const sourceOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.sources, t('pages.admin.audit.security.filters.any_source')),
);
const resultOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.audit.security.filters.any_result') },
    { value: 'succeeded', label: t('pages.admin.audit.result.succeeded') },
    { value: 'rejected', label: t('pages.admin.audit.result.rejected') },
    { value: 'failed', label: t('pages.admin.audit.result.failed') },
]);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        user: String(props.table.state.filters?.user ?? 'all'),
        action: String(props.table.state.filters?.action ?? 'all'),
        result: String(props.table.state.filters?.result ?? 'all'),
        source: String(props.table.state.filters?.source ?? 'all'),
        date_from: String(props.table.state.filters?.date_from ?? ''),
        date_to: String(props.table.state.filters?.date_to ?? ''),
    };
}

function allOptions(values: FilterOption[], label: string): FormSelectOption[] {
    return [{ value: 'all', label }, ...values];
}

function contextLabel(value: string): string {
    const keys: Record<string, string> = {
        'Actual actor': 'pages.admin.audit.security.context.actual_actor',
        Actor: 'pages.admin.audit.security.context.actor',
        'Impersonated user': 'pages.admin.audit.security.context.impersonated_user',
        'Target user': 'pages.admin.audit.security.context.target_user',
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
</script>

<template>
    <Head :title="t('pages.admin.audit.security.head_title')" />
    <AdminLayout
        :title="t('pages.admin.audit.security.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.audit.nav.label')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <OperationalMetricTile
                    :label="t('pages.admin.audit.security.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.security.metric.rejected')"
                    :value="summary.rejected"
                    :icon="IconAlertTriangle"
                    :tone="summary.rejected > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.security.metric.failed')"
                    :value="summary.failed"
                    :icon="IconAlertTriangle"
                    :tone="summary.failed > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.security.metric.impersonated')"
                    :value="summary.impersonated"
                    :icon="IconUserScan"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.security.metric.with_reason')"
                    :value="summary.withReason"
                    :icon="IconFingerprint"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.audit.security.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <FormSelect v-model="filters.user" :label="t('pages.admin.audit.security.filters.user')" :options="userOptions" />
                    <FormSelect v-model="filters.action" :label="t('pages.admin.audit.security.filters.action')" :options="actionOptions" />
                    <FormSelect v-model="filters.result" :label="t('pages.admin.audit.security.filters.result')" :options="resultOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.audit.security.filters.source')" :options="sourceOptions" />
                    <FormDateInput v-model="filters.date_from" :label="t('pages.admin.audit.security.filters.date_from')" />
                    <FormDateInput v-model="filters.date_to" :label="t('pages.admin.audit.security.filters.date_to')" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.audit.security.events.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.audit.security.events.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
