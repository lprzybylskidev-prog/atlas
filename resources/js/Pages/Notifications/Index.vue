<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconBell } from '@tabler/icons-vue';

import DataTable from '../../Components/DataTable.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../Types/data-table';

interface NotificationRow extends Record<string, unknown> {
    publicId: string;
    type: string;
    severity: string;
    title: string;
    body: string;
    teamPublicId: string;
    read: boolean;
    createdAt: string;
    readAt: string;
    deepLinkUrl: string;
}

defineProps<{
    notificationRows: NotificationRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator();
const columns: DataTableColumn<NotificationRow>[] = [
    { key: 'publicId', label: t('notifications.table.public_id'), hidden: true },
    { key: 'severity', label: t('notifications.table.severity'), format: 'severity' },
    { key: 'title', label: t('notifications.table.title') },
    { key: 'body', label: t('notifications.table.body') },
    { key: 'teamPublicId', label: t('notifications.table.team'), hidden: true },
    { key: 'read', label: t('notifications.table.read'), format: 'boolean' },
    { key: 'createdAt', label: t('notifications.table.created_at'), format: 'datetime' },
    { key: 'readAt', label: t('notifications.table.read_at'), format: 'datetime', hidden: true },
    { key: 'deepLinkUrl', label: t('notifications.table.deep_link'), hidden: true },
];
const actions: DataTableAction<NotificationRow>[] = [
    {
        key: 'open',
        label: t('notifications.open'),
        href: (row) => (row.deepLinkUrl === '' ? '/notifications' : row.deepLinkUrl),
        method: 'get',
        nativeNavigation: true,
        tone: 'info',
    },
    {
        key: 'mark-read',
        label: t('notifications.mark_read'),
        href: (row) => `/notifications/${row.publicId}/read`,
        method: 'post',
        tone: 'success',
    },
];
const bulkActions: DataTableBulkAction[] = [
    {
        key: 'mark-read',
        label: t('notifications.bulk.mark_read'),
        tone: 'success',
    },
];

function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): void {
    if (payload.action.key !== 'mark-read') {
        return;
    }

    router.post(
        '/notifications/read',
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
    <AppLayout :title="t('pages.notifications.title')" :title-icon="IconBell">
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
    </AppLayout>
</template>
