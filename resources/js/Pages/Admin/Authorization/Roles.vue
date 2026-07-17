<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconShieldCheck } from '@tabler/icons-vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface RoleRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    teamId: number | null;
    name: string;
    guard: string;
    permissionsCount: number;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    roles: RoleRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

const columns: DataTableColumn<RoleRow>[] = [
    { key: 'publicId', label: 'Public ID' },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'teamId', label: 'Team ID', hidden: true },
    { key: 'name', label: 'Role' },
    { key: 'guard', label: 'Guard' },
    { key: 'permissionsCount', label: 'Permissions' },
    { key: 'createdAt', label: 'Created at', format: 'datetime', hidden: true },
    { key: 'updatedAt', label: 'Updated at', format: 'datetime', hidden: true },
];

const actions: DataTableAction<RoleRow>[] = [
    { key: 'edit', label: 'Edit', href: (row) => `/admin/authorization/roles/${row.name}/edit` },
    { key: 'delete', label: 'Delete', method: 'delete', href: (row) => `/admin/authorization/roles/${row.name}` },
];
const bulkActions: DataTableBulkAction[] = [{ key: 'delete', label: 'Delete', tone: 'danger' }];
const roleNameByPublicId = new Map(props.roles.map((role) => [role.publicId, role.name]));

async function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    if (payload.action.key !== 'delete') {
        return;
    }

    await runBulkRecordAction(
        {
            method: 'delete',
            href: (rowId) => `/admin/authorization/roles/${roleNameByPublicId.get(rowId) ?? rowId}`,
        },
        payload.rowIds,
    );
}
</script>

<template>
    <Head :title="t('pages.admin.roles.head_title')" />
    <AdminLayout :title="t('pages.admin.roles.title')" :title-icon="IconShieldCheck">
        <section class="space-y-5">
            <div class="flex justify-end">
                <AdminActionLink href="/admin/authorization/roles/create" :icon="IconShieldCheck" tone="primary">
                    Create role
                </AdminActionLink>
            </div>

            <DataTable
                title="Roles"
                :rows="roles"
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
