<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconShieldCheck } from '@tabler/icons-vue';
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

const filters = reactive({
    guard: 'all',
    scope: 'all',
});

const guardOptions = computed(() => [
    { value: 'all', label: 'All guards' },
    ...Array.from(new Set(props.roles.map((role) => role.guard)))
        .sort((left, right) => left.localeCompare(right))
        .map((guard) => ({ value: guard, label: guard })),
]);

const scopeOptions = [
    { value: 'all', label: 'All scopes' },
    { value: 'global', label: 'Global' },
    { value: 'team', label: 'Team scoped' },
];

const filteredRoles = computed(() =>
    props.roles.filter((role) => {
        if (filters.guard !== 'all' && role.guard !== filters.guard) {
            return false;
        }

        if (filters.scope === 'global') {
            return role.teamId === null;
        }

        if (filters.scope === 'team') {
            return role.teamId !== null;
        }

        return true;
    }),
);

function resetFilters(): void {
    filters.guard = 'all';
    filters.scope = 'all';
}

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
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/authorization/roles/create" :icon="IconShieldCheck" tone="primary"> Create role </ActionLink>
            </div>

            <FilterPanel
                title="Role filters"
                :summary="`Showing ${filteredRoles.length} of ${roles.length} loaded roles.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.guard" label="Guard" :options="guardOptions" />
                    <FormSelect v-model="filters.scope" label="Scope" :options="scopeOptions" />
                </div>
            </FilterPanel>

            <DataTable
                title="Roles"
                :rows="filteredRoles"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :bulk-actions="bulkActions"
                :bulk-action-handler="handleBulkAction"
                :table="table"
                :filters="filters"
                ui-locale="en"
            />
        </PageStack>
    </AdminLayout>
</template>
