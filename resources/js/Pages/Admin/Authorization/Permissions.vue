<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import DataTable from '../../../Components/DataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableColumn } from '../../../Types/data-table';
import { useTranslator } from '../../../Localization/translator';

interface PermissionRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    name: string;
    guard: string;
    description: string;
    module: string;
    teamScoped: boolean;
    moduleActivation: string;
    assigned: boolean;
    effective: boolean;
    ineffectiveReason: string | null;
    createdAt: string;
    updatedAt: string;
}

defineProps<{
    permissions: PermissionRow[];
}>();

const { t } = useTranslator('en');
const columns: DataTableColumn<PermissionRow>[] = [
    { key: 'publicId', label: 'Public ID' },
    { key: 'id', label: 'ID', hidden: true },
    { key: 'name', label: 'Permission' },
    { key: 'guard', label: 'Guard', hidden: true },
    { key: 'description', label: 'Description' },
    { key: 'module', label: 'Module' },
    { key: 'teamScoped', label: 'Team scoped', format: 'boolean' },
    { key: 'moduleActivation', label: 'Module state' },
    { key: 'assigned', label: 'Assigned', format: 'boolean' },
    { key: 'effective', label: 'Effective', format: 'boolean' },
    { key: 'ineffectiveReason', label: 'Ineffective reason', hidden: true },
    { key: 'createdAt', label: 'Created at', format: 'datetime', hidden: true },
    { key: 'updatedAt', label: 'Updated at', format: 'datetime', hidden: true },
];
</script>

<template>
    <Head :title="t('pages.admin.permissions.head_title')" />
    <AdminLayout :title="t('pages.admin.permissions.title')">
        <section class="space-y-5">
            <DataTable
                title="Permissions"
                :rows="permissions"
                :columns="columns"
                row-key="publicId"
                ui-locale="en"
                state-key="admin.authorization.permissions"
            />
        </section>
    </AdminLayout>
</template>
