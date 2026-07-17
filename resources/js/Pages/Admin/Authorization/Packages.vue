<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPackages } from '@tabler/icons-vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import { runBulkRecordAction } from '../../../Composables/useBulkRecordActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableBulkAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface PackageRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    label: string;
    initialRoles: string[];
    directPermissions: string[];
    templatePermissions: string[];
    isActive: boolean;
    createdAt: string;
    updatedAt: string;
}

defineProps<{
    packages: PackageRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

const columns: DataTableColumn<PackageRow>[] = [
    { key: 'publicId', label: 'Public ID' },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'teamName', label: 'Team' },
    { key: 'teamPublicId', label: 'Team public ID', hidden: true },
    { key: 'label', label: 'Label' },
    { key: 'name', label: 'Name' },
    { key: 'initialRoles', label: 'Initial roles', format: 'list' },
    { key: 'directPermissions', label: 'Direct permissions', format: 'count', hidden: true },
    { key: 'templatePermissions', label: 'Template permissions', format: 'count', hidden: true },
    { key: 'isActive', label: 'Active', format: 'boolean', hidden: true },
    { key: 'createdAt', label: 'Created at', format: 'datetime', hidden: true },
    { key: 'updatedAt', label: 'Updated at', format: 'datetime', hidden: true },
];

const actions: DataTableAction<PackageRow>[] = [
    { key: 'edit', label: 'Edit', href: (row) => `/admin/authorization/packages/${row.publicId}/edit` },
    { key: 'delete', label: 'Deactivate', method: 'delete', href: (row) => `/admin/authorization/packages/${row.publicId}` },
];
const bulkActions: DataTableBulkAction[] = [{ key: 'delete', label: 'Deactivate', tone: 'danger' }];

async function handleBulkAction(payload: { action: DataTableBulkAction; rowIds: string[] }): Promise<void> {
    if (payload.action.key !== 'delete') {
        return;
    }

    await runBulkRecordAction(
        {
            method: 'delete',
            href: (rowId) => `/admin/authorization/packages/${rowId}`,
        },
        payload.rowIds,
    );
}
</script>

<template>
    <Head :title="t('pages.admin.packages.head_title')" />
    <AdminLayout :title="t('pages.admin.packages.title')" :title-icon="IconPackages">
        <section class="space-y-5">
            <div class="flex justify-end">
                <AdminActionLink href="/admin/authorization/packages/create" :icon="IconPackages" tone="primary">
                    Create preset
                </AdminActionLink>
            </div>

            <DataTable
                title="Presets"
                :rows="packages"
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
