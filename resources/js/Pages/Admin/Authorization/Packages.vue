<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPackages } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
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

const props = defineProps<{
    packages: PackageRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator();

const filters = reactive({
    status: 'all',
    team: 'all',
});

const statusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const teamOptions = computed(() => [
    { value: 'all', label: 'All teams' },
    ...Array.from(new Map(props.packages.map((preset) => [preset.teamPublicId, preset.teamName])).entries())
        .sort((left, right) => left[1].localeCompare(right[1]))
        .map(([value, label]) => ({ value, label })),
]);

const filteredPackages = computed(() =>
    props.packages.filter((preset) => {
        if (filters.status === 'active' && !preset.isActive) {
            return false;
        }

        if (filters.status === 'inactive' && preset.isActive) {
            return false;
        }

        return filters.team === 'all' || preset.teamPublicId === filters.team;
    }),
);

function resetFilters(): void {
    filters.status = 'all';
    filters.team = 'all';
}

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
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/authorization/packages/create" :icon="IconPackages" tone="primary"> Create preset </ActionLink>
            </div>

            <FilterPanel
                title="Preset filters"
                :summary="`Showing ${filteredPackages.length} of ${packages.length} loaded presets.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.status" label="Status" :options="statusOptions" />
                    <FormSelect v-model="filters.team" label="Team" :options="teamOptions" />
                </div>
            </FilterPanel>

            <DataTable
                title="Presets"
                :rows="filteredPackages"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="filters"
            />
        </PageStack>
    </AdminLayout>
</template>
