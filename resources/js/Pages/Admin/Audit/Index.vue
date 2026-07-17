<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconClipboardList } from '@tabler/icons-vue';
import { reactive, watch } from 'vue';

import AdminFilterPanel from '../../../Components/AdminFilterPanel.vue';
import DataTable from '../../../Components/DataTable.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface AuditEventRow extends Record<string, unknown> {
    id: number | null;
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

interface AuditFilters {
    actor: string;
    actualActor: string;
    impersonatedUser: string;
    impersonationSession: string;
    target: string;
    targetType: string;
    action: string;
    team: string;
    module: string;
    source: string;
    correlation: string;
    result: string;
    security: string;
    dateFrom: string;
    dateTo: string;
}

interface AuditFilterOption {
    value: string;
    label: string;
}

interface AuditFilterOptions {
    modules: AuditFilterOption[];
    actions: AuditFilterOption[];
    sources: AuditFilterOption[];
    targetTypes: AuditFilterOption[];
    teams: AuditFilterOption[];
}

const props = defineProps<{
    events: AuditEventRow[];
    table: DataTableMeta;
    filters: AuditFilters;
    filterOptions: AuditFilterOptions;
}>();

const { t } = useTranslator('en');
const filters = reactive<AuditFilters>({ ...props.filters });
const resultOptions = [
    { value: '', label: 'Any result' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'failed', label: 'Failed' },
];
const securityOptions = [
    { value: '', label: 'Any audit type' },
    { value: 'yes', label: 'Security only' },
    { value: 'no', label: 'Application only' },
];
const anyOption = (label: string): AuditFilterOption => ({ value: '', label });
const moduleOptions = [anyOption('Any module'), ...props.filterOptions.modules];
const actionOptions = [anyOption('Any action'), ...props.filterOptions.actions];
const sourceOptions = [anyOption('Any source'), ...props.filterOptions.sources];
const targetTypeOptions = [anyOption('Any target type'), ...props.filterOptions.targetTypes];
const teamOptions = [anyOption('Any team'), ...props.filterOptions.teams];
const tableQueryKeys = ['per_page', 'sort', 'direction', 'search', 'columns', 'column_order', 'view'] as const;
const selectedViewStorageKey = `atlas.table.${props.table.key}.selectedView`;

const columns: DataTableColumn<AuditEventRow>[] = [
    { key: 'publicId', label: 'Public ID', hidden: true },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'occurredAt', label: 'Occurred at', format: 'datetime' },
    { key: 'module', label: 'Module' },
    { key: 'action', label: 'Action' },
    { key: 'result', label: 'Result', format: 'status' },
    { key: 'source', label: 'Source' },
    { key: 'actorPublicId', label: 'Actor' },
    { key: 'actualActorPublicId', label: 'Actual actor', hidden: true },
    { key: 'impersonatedUserPublicId', label: 'Impersonated user', hidden: true },
    { key: 'impersonationSessionId', label: 'Impersonation session', hidden: true },
    { key: 'targetType', label: 'Target type' },
    { key: 'targetPublicId', label: 'Target' },
    { key: 'aggregateType', label: 'Aggregate type', hidden: true },
    { key: 'aggregatePublicId', label: 'Aggregate', hidden: true },
    { key: 'teamPublicId', label: 'Team' },
    { key: 'correlationId', label: 'Correlation ID' },
    { key: 'reason', label: 'Reason', hidden: true },
    { key: 'security', label: 'Security', format: 'boolean' },
    { key: 'metadata', label: 'Metadata keys', hidden: true },
];

function applyFilters(): void {
    const tableState = currentTableQuery();

    router.get(
        '/admin/audit',
        {
            ...tableState,
            page: 1,
            actor: filters.actor,
            actual_actor: filters.actualActor,
            impersonated_user: filters.impersonatedUser,
            impersonation_session: filters.impersonationSession,
            target: filters.target,
            target_type: filters.targetType,
            action: filters.action,
            team: filters.team,
            module: filters.module,
            source: filters.source,
            correlation: filters.correlation,
            result: filters.result,
            security: filters.security,
            date_from: filters.dateFrom,
            date_to: filters.dateTo,
        },
        { preserveScroll: true, preserveState: false, replace: true },
    );
}

function currentTableQuery(): Record<string, string> {
    if (typeof window === 'undefined') {
        return props.table.state.view === null ? {} : { view: props.table.state.view };
    }

    const params = new URLSearchParams(window.location.search);
    const state: Record<string, string> = {};

    for (const key of tableQueryKeys) {
        const value = params.get(key);

        if (value !== null && value !== '') {
            state[key] = value;
        }
    }

    if (state.view === undefined && props.table.state.view !== null) {
        state.view = props.table.state.view;
    }

    if (state.view === undefined) {
        const storedView = window.sessionStorage.getItem(selectedViewStorageKey);

        if (storedView !== null && storedView !== '') {
            state.view = storedView;
        }
    }

    return state;
}

function clearFilters(): void {
    filters.actor = '';
    filters.actualActor = '';
    filters.impersonatedUser = '';
    filters.impersonationSession = '';
    filters.target = '';
    filters.targetType = '';
    filters.action = '';
    filters.team = '';
    filters.module = '';
    filters.source = '';
    filters.correlation = '';
    filters.result = '';
    filters.security = '';
    filters.dateFrom = '';
    filters.dateTo = '';
    applyFilters();
}

watch(
    () => props.filters,
    (nextFilters) => {
        Object.assign(filters, nextFilters);
    },
    { deep: true },
);
</script>

<template>
    <Head title="Audit" />
    <AdminLayout :title="t('pages.admin.audit.title')" :title-icon="IconClipboardList">
        <section class="space-y-4">
            <AdminFilterPanel @apply="applyFilters" @clear="clearFilters">
                <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <FormSelect v-model="filters.module" class="mt-1" aria-label="Module" :options="moduleOptions" />
                    <FormSelect v-model="filters.action" class="mt-1" aria-label="Action" :options="actionOptions" />
                    <FormSelect v-model="filters.source" class="mt-1" aria-label="Source" :options="sourceOptions" />
                    <FormSelect v-model="filters.targetType" class="mt-1" aria-label="Target type" :options="targetTypeOptions" />
                    <FormSelect v-model="filters.team" class="mt-1" aria-label="Team" :options="teamOptions" />
                    <FormSelect v-model="filters.result" class="mt-1" aria-label="Result" :options="resultOptions" />
                    <FormSelect v-model="filters.security" class="mt-1" aria-label="Security filter" :options="securityOptions" />
                    <FormInput v-model="filters.actor" aria-label="Actor public ID" placeholder="Actor" />
                    <FormInput v-model="filters.actualActor" aria-label="Actual actor public ID" placeholder="Actual actor" />
                    <FormInput
                        v-model="filters.impersonatedUser"
                        aria-label="Impersonated user public ID"
                        placeholder="Impersonated user"
                    />
                    <FormInput
                        v-model="filters.impersonationSession"
                        aria-label="Impersonation session ID"
                        placeholder="Impersonation session"
                    />
                    <FormInput v-model="filters.target" aria-label="Target public ID" placeholder="Target" />
                    <FormInput v-model="filters.correlation" aria-label="Correlation ID" placeholder="Correlation ID" />
                    <FormInput v-model="filters.dateFrom" aria-label="Date from" type="date" />
                    <FormInput v-model="filters.dateTo" aria-label="Date to" type="date" />
                </div>
            </AdminFilterPanel>
            <DataTable title="Audit events" :rows="events" :columns="columns" row-key="publicId" :table="table" ui-locale="en" />
        </section>
    </AdminLayout>
</template>
