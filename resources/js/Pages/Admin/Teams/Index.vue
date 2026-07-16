<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';

import DataTable from '../../../Components/DataTable.vue';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface TeamRow extends Record<string, unknown> {
    id: number;
    publicId: string;
    name: string;
    isActive: boolean;
    createdAt: string;
    updatedAt: string;
}

defineProps<{
    teams: TeamRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

const columns: DataTableColumn<TeamRow>[] = [
    { key: 'publicId', label: 'Public ID' },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'name', label: 'Name' },
    { key: 'isActive', label: 'Active', format: 'boolean' },
    { key: 'createdAt', label: 'Created at', format: 'datetime', hidden: true },
    { key: 'updatedAt', label: 'Updated at', format: 'datetime', hidden: true },
];

const actions: DataTableAction<TeamRow>[] = [
    { key: 'edit', label: 'Edit', href: (row) => `/admin/teams/${row.publicId}/edit` },
    { key: 'activate', label: 'Activate', method: 'post', href: (row) => `/admin/teams/${row.publicId}/activate` },
    { key: 'deactivate', label: 'Deactivate', method: 'post', href: (row) => `/admin/teams/${row.publicId}/deactivate` },
    { key: 'delete', label: 'Delete', method: 'delete', href: (row) => `/admin/teams/${row.publicId}` },
];

const bulkActions: DataTableBulkAction[] = [
    { key: 'activate', label: 'Activate', tone: 'success' },
    { key: 'deactivate', label: 'Deactivate', tone: 'danger' },
    { key: 'delete', label: 'Delete', tone: 'danger' },
];

async function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    if (payload.action.key === 'delete') {
        await runBulkRecordAction(
            {
                method: 'delete',
                href: (rowId) => `/admin/teams/${rowId}`,
            },
            payload.rowIds,
        );

        return;
    }

    await runBulkRecordAction(
        {
            method: 'post',
            href: (rowId) => `/admin/teams/${rowId}/${payload.action.key}`,
        },
        payload.rowIds,
    );
}
</script>

<template>
    <Head :title="t('pages.admin.teams.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.title')" :title-icon="IconUsersGroup">
        <section class="space-y-5">
            <div class="flex justify-end">
                <Link
                    href="/admin/teams/create"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-teal-700 px-4 text-sm font-medium text-white transition hover:bg-teal-800 focus-visible:outline focus-visible:outline-amber-500 dark:bg-teal-600 dark:hover:bg-teal-500"
                >
                    <IconUsersGroup aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Create team
                </Link>
            </div>

            <DataTable
                title="Teams"
                :rows="teams"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                ui-locale="en"
            />
        </section>
    </AdminLayout>
</template>
