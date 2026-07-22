<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';
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

interface TeamRow extends Record<string, unknown> {
    id: number;
    publicId: string;
    name: string;
    isActive: boolean;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    teams: TeamRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator();

const filters = reactive({
    status: 'all',
});

const statusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const filteredTeams = computed(() =>
    props.teams.filter((team) => {
        if (filters.status === 'active') {
            return team.isActive;
        }

        if (filters.status === 'inactive') {
            return !team.isActive;
        }

        return true;
    }),
);

function resetFilters(): void {
    filters.status = 'all';
}

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
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/teams/create" :icon="IconUsersGroup" tone="primary"> Create team </ActionLink>
            </div>

            <FilterPanel
                title="Team filters"
                :summary="`Showing ${filteredTeams.length} of ${teams.length} loaded teams.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.status" label="Status" :options="statusOptions" />
                </div>
            </FilterPanel>

            <DataTable
                title="Teams"
                :rows="filteredTeams"
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
