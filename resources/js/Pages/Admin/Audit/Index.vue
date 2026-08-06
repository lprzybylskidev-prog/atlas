<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconHistory, IconListDetails, IconSearch, IconShieldCheck, IconUserScan } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ShellSubnavigationItem } from '../../../Types/navigation';
import { existingOptionsWithAll } from '../../../Utils/filterOptions';
import { moduleLabel } from '../../../Utils/moduleLabels';
import { readableFilterOption, readableToken } from '../../../Utils/readableTokens';

interface FilterOption {
    value: string;
    label: string;
}

interface AuditEventRow extends Record<string, unknown> {
    publicId: string;
    occurredAt: string;
    module: string;
    action: string;
    result: string;
    source: string;
    actorPublicId: string;
    actualActorPublicId: string;
    impersonatedUserPublicId: string;
    impersonationSessionId: string;
    targetType: string;
    targetPublicId: string;
    aggregateType: string;
    aggregatePublicId: string;
    teamPublicId: string;
    correlationId: string;
    reason: string;
    security: boolean;
    metadata: string;
}

interface AuditSummary {
    visible: number;
    security: number;
    rejected: number;
    failed: number;
    impersonated: number;
    withReason: number;
}

const props = defineProps<{
    events: AuditEventRow[];
    summary: AuditSummary;
    table: DataTableMeta;
    filterOptions: {
        modules: FilterOption[];
        actions: FilterOption[];
        sources: FilterOption[];
        targetTypes: FilterOption[];
        teams: FilterOption[];
    };
}>();

const { locale, t } = useTranslator();
const filterKeys = [
    'actor',
    'actual_actor',
    'impersonated_user',
    'impersonation_session',
    'target',
    'target_type',
    'action',
    'team',
    'module',
    'source',
    'correlation',
    'result',
    'security',
    'date_from',
    'date_to',
];
const filterDefaults = {
    actor: '',
    actual_actor: '',
    impersonated_user: '',
    impersonation_session: '',
    target: '',
    target_type: 'all',
    action: 'all',
    team: 'all',
    module: 'all',
    source: 'all',
    correlation: '',
    result: 'all',
    security: 'all',
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
        active: true,
    },
    {
        key: 'audit.security',
        label: t('pages.admin.audit.nav.security_history'),
        href: '/admin/audit/security-history',
        icon: IconShieldCheck,
        active: false,
    },
]);
const columns = computed<DataTableColumn<AuditEventRow>[]>(() => [
    { key: 'occurredAt', label: t('pages.admin.audit.table.occurred_at'), format: 'datetime' },
    { key: 'module', label: t('pages.admin.audit.table.module'), format: 'status' },
    { key: 'action', label: t('pages.admin.audit.table.action') },
    { key: 'result', label: t('pages.admin.audit.table.result'), format: 'status' },
    { key: 'source', label: t('pages.admin.audit.table.source'), format: 'status' },
    { key: 'actorPublicId', label: t('pages.admin.audit.table.actor') },
    { key: 'targetType', label: t('pages.admin.audit.table.target_type'), format: 'status' },
    { key: 'targetPublicId', label: t('pages.admin.audit.table.target') },
    { key: 'teamPublicId', label: t('pages.admin.audit.table.team') },
    { key: 'correlationId', label: t('pages.admin.audit.table.correlation') },
    { key: 'security', label: t('pages.admin.audit.table.security'), format: 'boolean' },
    { key: 'actualActorPublicId', label: t('pages.admin.audit.table.actual_actor'), hidden: true },
    { key: 'impersonatedUserPublicId', label: t('pages.admin.audit.table.impersonated_user'), hidden: true },
    { key: 'impersonationSessionId', label: t('pages.admin.audit.table.impersonation_session'), hidden: true },
    { key: 'aggregateType', label: t('pages.admin.audit.table.aggregate_type'), hidden: true },
    { key: 'aggregatePublicId', label: t('pages.admin.audit.table.aggregate'), hidden: true },
    { key: 'reason', label: t('pages.admin.audit.table.reason'), hidden: true },
    { key: 'metadata', label: t('pages.admin.audit.table.metadata'), hidden: true },
    { key: 'publicId', label: t('pages.admin.audit.table.public_id'), hidden: true },
]);
const actions = computed<DataTableAction<AuditEventRow>[]>(() => [
    {
        key: 'open',
        label: t('pages.admin.audit.actions.open_impersonation_session'),
        href: (row) => `/admin/audit/impersonation/${encodeURIComponent(row.impersonationSessionId)}`,
        method: 'get',
        visible: (row) => row.impersonationSessionId !== '',
        tone: 'info',
    },
]);
const moduleOptions = computed<FormSelectOption[]>(() =>
    existingOptionsWithAll(props.filterOptions.modules, t('pages.admin.audit.filters.any_module'), (option) =>
        moduleLabel(option.value, t),
    ),
);
const actionOptions = computed<FormSelectOption[]>(() =>
    existingOptionsWithAll(props.filterOptions.actions, t('pages.admin.audit.filters.any_action'), readableFilterOption),
);
const sourceOptions = computed<FormSelectOption[]>(() =>
    existingOptionsWithAll(props.filterOptions.sources, t('pages.admin.audit.filters.any_source'), readableFilterOption),
);
const targetTypeOptions = computed<FormSelectOption[]>(() =>
    existingOptionsWithAll(props.filterOptions.targetTypes, t('pages.admin.audit.filters.any_target_type'), readableFilterOption),
);
const teamOptions = computed<FormSelectOption[]>(() =>
    existingOptionsWithAll(props.filterOptions.teams, t('pages.admin.audit.filters.any_team')),
);
const resultOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.audit.filters.any_result') },
    { value: 'succeeded', label: t('pages.admin.audit.result.succeeded') },
    { value: 'rejected', label: t('pages.admin.audit.result.rejected') },
    { value: 'failed', label: t('pages.admin.audit.result.failed') },
]);
const securityOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.audit.filters.any_security') },
    { value: 'yes', label: t('pages.admin.audit.filters.security_only') },
    { value: 'no', label: t('pages.admin.audit.filters.non_security') },
]);
const tableFilters = computed(() => filterValues());
const rows = computed<AuditEventRow[]>(() =>
    props.events.map((event) => ({
        ...event,
        module: moduleLabel(event.module, t),
        action: readableToken(event.action),
        source: readableToken(event.source),
        targetType: readableToken(event.targetType),
        aggregateType: readableToken(event.aggregateType),
    })),
);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        actor: String(props.table.state.filters?.actor ?? ''),
        actual_actor: String(props.table.state.filters?.actual_actor ?? ''),
        impersonated_user: String(props.table.state.filters?.impersonated_user ?? ''),
        impersonation_session: String(props.table.state.filters?.impersonation_session ?? ''),
        target: String(props.table.state.filters?.target ?? ''),
        target_type: String(props.table.state.filters?.target_type ?? 'all'),
        action: String(props.table.state.filters?.action ?? 'all'),
        team: String(props.table.state.filters?.team ?? 'all'),
        module: String(props.table.state.filters?.module ?? 'all'),
        source: String(props.table.state.filters?.source ?? 'all'),
        correlation: String(props.table.state.filters?.correlation ?? ''),
        result: String(props.table.state.filters?.result ?? 'all'),
        security: String(props.table.state.filters?.security ?? 'all'),
        date_from: String(props.table.state.filters?.date_from ?? ''),
        date_to: String(props.table.state.filters?.date_to ?? ''),
    };
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
    <Head :title="t('pages.admin.audit.head_title')" />
    <AppLayout
        mode="admin"
        :title="t('pages.admin.audit.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.audit.nav.label')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.security')"
                    :value="summary.security"
                    :icon="IconShieldCheck"
                    :tone="summary.security > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.rejected')"
                    :value="summary.rejected"
                    :icon="IconAlertTriangle"
                    :tone="summary.rejected > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.failed')"
                    :value="summary.failed"
                    :icon="IconAlertTriangle"
                    :tone="summary.failed > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.impersonated')"
                    :value="summary.impersonated"
                    :icon="IconUserScan"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.metric.with_reason')"
                    :value="summary.withReason"
                    :icon="IconSearch"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.audit.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.module" :label="t('pages.admin.audit.filters.module')" :options="moduleOptions" />
                    <FormSelect v-model="filters.action" :label="t('pages.admin.audit.filters.action')" :options="actionOptions" />
                    <FormSelect v-model="filters.result" :label="t('pages.admin.audit.filters.result')" :options="resultOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.audit.filters.source')" :options="sourceOptions" />
                    <FormSelect v-model="filters.security" :label="t('pages.admin.audit.filters.security')" :options="securityOptions" />
                    <FormSelect
                        v-model="filters.target_type"
                        :label="t('pages.admin.audit.filters.target_type')"
                        :options="targetTypeOptions"
                    />
                    <FormSelect v-model="filters.team" :label="t('pages.admin.audit.filters.team')" :options="teamOptions" />
                    <FormDateInput v-model="filters.date_from" :label="t('pages.admin.audit.filters.date_from')" />
                    <FormDateInput v-model="filters.date_to" :label="t('pages.admin.audit.filters.date_to')" />
                    <FormInput v-model="filters.actor" :label="t('pages.admin.audit.filters.actor')" />
                    <FormInput v-model="filters.target" :label="t('pages.admin.audit.filters.target')" />
                    <FormInput v-model="filters.correlation" :label="t('pages.admin.audit.filters.correlation')" />
                    <FormInput v-model="filters.actual_actor" :label="t('pages.admin.audit.filters.actual_actor')" />
                    <FormInput v-model="filters.impersonated_user" :label="t('pages.admin.audit.filters.impersonated_user')" />
                    <FormInput v-model="filters.impersonation_session" :label="t('pages.admin.audit.filters.impersonation_session')" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.audit.events.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.audit.events.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
