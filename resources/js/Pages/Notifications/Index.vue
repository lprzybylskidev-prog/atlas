<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconBell, IconExternalLink, IconInbox, IconMailOpened } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../Components/DataTable.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../Composables/useTableFilterControls';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../Types/data-table';
import { optionsWithAll } from '../../Utils/filterOptions';
import { formatStatus } from '../../Utils/formatters';

interface NotificationRow extends Record<string, unknown> {
    publicId: string;
    type: string;
    severity: string;
    title: string;
    body: string;
    teamPublicId: string;
    scope: string;
    scopeLabel: string;
    read: boolean;
    createdAt: string;
    readAt: string;
    deepLinkUrl: string;
}

interface NotificationSummary {
    total: number;
    visible: number;
    unread: number;
    read: number;
    warnings: number;
    danger: number;
    withLinks: number;
}

const props = defineProps<{
    notificationRows: NotificationRow[];
    summary: NotificationSummary;
    filterOptions: {
        severities: string[];
        types: string[];
    };
    table: DataTableMeta;
}>();

const { t } = useTranslator();
const filterKeys = ['status', 'severity', 'scope', 'type', 'link', 'from', 'to'];
const filterDefaults = {
    status: 'all',
    severity: 'all',
    scope: 'all',
    type: 'all',
    link: 'all',
    from: '',
    to: '',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const columns = computed<DataTableColumn<NotificationRow>[]>(() => [
    { key: 'publicId', label: t('notifications.table.public_id'), hidden: true },
    { key: 'severity', label: t('notifications.table.severity'), format: 'severity' },
    { key: 'title', label: t('notifications.table.title') },
    { key: 'body', label: t('notifications.table.body') },
    { key: 'teamPublicId', label: t('notifications.table.team'), hidden: true },
    { key: 'scopeLabel', label: t('notifications.table.scope') },
    { key: 'read', label: t('notifications.table.read'), format: 'boolean' },
    { key: 'createdAt', label: t('notifications.table.created_at'), format: 'datetime' },
    { key: 'readAt', label: t('notifications.table.read_at'), format: 'datetime', hidden: true },
    { key: 'deepLinkUrl', label: t('notifications.table.deep_link'), hidden: true },
]);
const actions = computed<DataTableAction<NotificationRow>[]>(() => [
    {
        key: 'open',
        label: t('notifications.open'),
        href: (row) => (row.deepLinkUrl === '' ? '/user/notifications' : row.deepLinkUrl),
        method: 'get',
        nativeNavigation: true,
        tone: 'info',
        visible: (row) => row.deepLinkUrl !== '',
    },
    {
        key: 'mark-read',
        label: t('notifications.mark_read'),
        href: (row) => `/user/notifications/${row.publicId}/read`,
        method: 'post',
        tone: 'success',
        visible: (row) => !row.read,
    },
]);
const bulkActions = computed<DataTableBulkAction[]>(() => [
    {
        key: 'mark-read',
        label: t('notifications.bulk.mark_read'),
        tone: 'success',
    },
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('notifications.filters.any_status') },
    { value: 'unread', label: t('notifications.filters.unread') },
    { value: 'read', label: t('notifications.filters.read') },
]);
const severityOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.severities, t('notifications.filters.any_severity'), severityLabel),
);
const scopeOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('notifications.filters.any_scope') },
    { value: 'team', label: t('notifications.scope.team') },
    { value: 'global', label: t('notifications.scope.global') },
]);
const typeOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.types, t('notifications.filters.any_type'), formatStatus),
);
const linkOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('notifications.filters.any_link') },
    { value: 'with_link', label: t('notifications.filters.with_link') },
    { value: 'without_link', label: t('notifications.filters.without_link') },
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
        severity: String(props.table.state.filters?.severity ?? 'all'),
        scope: String(props.table.state.filters?.scope ?? 'all'),
        type: String(props.table.state.filters?.type ?? 'all'),
        link: String(props.table.state.filters?.link ?? 'all'),
        from: String(props.table.state.filters?.from ?? ''),
        to: String(props.table.state.filters?.to ?? ''),
    };
}

function severityLabel(severity: string): string {
    const keys: Record<string, string> = {
        critical: 'notifications.severity.critical',
        error: 'notifications.severity.error',
        info: 'notifications.severity.info',
        success: 'notifications.severity.success',
        warning: 'notifications.severity.warning',
    };

    return keys[severity] === undefined ? formatStatus(severity) : t(keys[severity]);
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): void {
    if (payload.action.key !== 'mark-read') {
        return;
    }

    router.post(
        '/user/notifications/read',
        { notifications: payload.rowIds },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
}
</script>

<template>
    <Head :title="t('pages.notifications.head_title')" />
    <AppLayout :title="t('pages.notifications.title')" :title-icon="IconBell" mode="user">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile :label="t('notifications.metric.total')" :value="summary.total" :icon="IconInbox" tone="teal" />
                <OperationalMetricTile :label="t('notifications.metric.visible')" :value="summary.visible" :icon="IconBell" tone="sky" />
                <OperationalMetricTile
                    :label="t('notifications.metric.unread')"
                    :value="summary.unread"
                    :icon="IconBell"
                    :tone="summary.unread > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile :label="t('notifications.metric.read')" :value="summary.read" :icon="IconMailOpened" tone="zinc" />
                <OperationalMetricTile
                    :label="t('notifications.metric.warning')"
                    :value="summary.warnings + summary.danger"
                    :icon="IconAlertTriangle"
                    :tone="summary.warnings + summary.danger > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('notifications.metric.with_links')"
                    :value="summary.withLinks"
                    :icon="IconExternalLink"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('notifications.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <FormSelect v-model="filters.status" :label="t('notifications.filters.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.severity" :label="t('notifications.filters.severity')" :options="severityOptions" />
                    <FormSelect v-model="filters.scope" :label="t('notifications.filters.scope')" :options="scopeOptions" />
                    <FormSelect v-model="filters.type" :label="t('notifications.filters.type')" :options="typeOptions" />
                    <FormSelect v-model="filters.link" :label="t('notifications.filters.link')" :options="linkOptions" />
                    <FormDateInput v-model="filters.from" :label="t('notifications.filters.from')" />
                    <FormDateInput v-model="filters.to" :label="t('notifications.filters.to')" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.notifications.title')"
                :rows="notificationRows"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
            />
        </PageStack>
    </AppLayout>
</template>
