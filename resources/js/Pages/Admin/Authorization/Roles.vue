<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconShieldCheck } from '@tabler/icons-vue';

import DataTable from '../../../Components/DataTable.vue';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn } from '../../../Types/data-table';

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
                <Link
                    href="/admin/authorization/roles/create"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-teal-700 px-4 text-sm font-medium text-white transition hover:bg-teal-800 focus-visible:outline focus-visible:outline-amber-500 dark:bg-teal-600 dark:hover:bg-teal-500"
                >
                    <IconShieldCheck aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Create role
                </Link>
            </div>

            <DataTable
                title="Roles"
                :rows="roles"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                ui-locale="en"
                state-key="admin.authorization.roles"
            />
        </section>
    </AdminLayout>
</template>
