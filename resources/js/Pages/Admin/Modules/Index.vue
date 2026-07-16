<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPuzzle } from '@tabler/icons-vue';

import DataTable from '../../../Components/DataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface ModuleRow extends Record<string, unknown> {
    moduleKey: string;
    category: string;
    technicallyAvailable: boolean;
    globallyEnabled: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    teamStateSource: string;
    supportsGlobalActivation: boolean;
    supportsTeamActivation: boolean;
    requiredDependencies: string;
    optionalDependencies: string;
}

defineProps<{
    modules: ModuleRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');
const columns: DataTableColumn<ModuleRow>[] = [
    { key: 'moduleKey', label: 'Module' },
    { key: 'category', label: 'Category' },
    { key: 'technicallyAvailable', label: 'Available', format: 'boolean' },
    { key: 'globallyEnabled', label: 'Global', format: 'boolean' },
    { key: 'teamEnabled', label: 'Active team', format: 'boolean' },
    { key: 'effectiveEnabled', label: 'Effective', format: 'boolean' },
    { key: 'teamStateSource', label: 'Team source' },
    { key: 'supportsGlobalActivation', label: 'Global support', format: 'boolean', hidden: true },
    { key: 'supportsTeamActivation', label: 'Team support', format: 'boolean', hidden: true },
    { key: 'requiredDependencies', label: 'Required dependencies', hidden: true },
    { key: 'optionalDependencies', label: 'Optional dependencies', hidden: true },
];
const actions: DataTableAction<ModuleRow>[] = [{ key: 'show', label: 'Manage teams', href: (row) => `/admin/modules/${row.moduleKey}` }];
</script>

<template>
    <Head title="Modules" />
    <AdminLayout :title="t('navigation.modules')" :title-icon="IconPuzzle">
        <DataTable
            title="Modules"
            :rows="modules"
            :columns="columns"
            row-key="moduleKey"
            :actions="actions"
            :table="table"
            ui-locale="en"
        />
    </AdminLayout>
</template>
