<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconShieldLock, IconShieldPlus } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface RoleRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    name: string;
    displayName: string;
    guard: string;
    permissionsCount: number;
    assignedUsersCount: number;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    roles: RoleRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['assignment', 'permissions'];
const filterDefaults = {
    assignment: 'all',
    permissions: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const columns = computed<DataTableColumn<RoleRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.roles.table.public_id') },
    { key: 'id', label: t('pages.admin.roles.table.internal_id'), hidden: true },
    { key: 'displayName', label: t('pages.admin.roles.table.display_name') },
    { key: 'name', label: t('pages.admin.roles.table.technical_name'), hidden: true },
    { key: 'guard', label: t('pages.admin.roles.table.guard') },
    { key: 'permissionsCount', label: t('pages.admin.roles.table.permissions_count'), format: 'number' },
    { key: 'assignedUsersCount', label: t('pages.admin.roles.table.assigned_users_count'), format: 'number', hidden: true },
    { key: 'createdAt', label: t('pages.admin.roles.table.created_at'), format: 'datetime', hidden: true },
    { key: 'updatedAt', label: t('pages.admin.roles.table.updated_at'), format: 'datetime', hidden: true },
]);
const assignmentOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.roles.filters.any_assignment') },
    { value: 'assigned', label: t('pages.admin.roles.filters.assigned') },
    { value: 'unassigned', label: t('pages.admin.roles.filters.unassigned') },
]);
const permissionOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.roles.filters.any_permissions') },
    { value: 'with', label: t('pages.admin.roles.filters.with_permissions') },
    { value: 'without', label: t('pages.admin.roles.filters.without_permissions') },
]);
const actions = computed<DataTableAction<RoleRow>[]>(() => [
    {
        key: 'edit',
        label: t('pages.admin.roles.actions.edit'),
        href: (role) => `/admin/authorization/roles/${encodeURIComponent(role.name)}/edit`,
    },
    {
        key: 'delete',
        label: t('pages.admin.roles.actions.delete'),
        method: 'delete',
        href: (role) => `/admin/authorization/roles/${encodeURIComponent(role.name)}`,
        confirm: (role) => t('pages.admin.roles.actions.delete_confirm', { role: role.displayName }),
        disabled: (role) => role.assignedUsersCount > 0,
        disabledReason: (role) => t('pages.admin.roles.actions.delete_disabled_assigned', { count: role.assignedUsersCount }),
        tone: 'danger',
    },
]);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        assignment: String(props.table.state.filters?.assignment ?? 'all'),
        permissions: String(props.table.state.filters?.permissions ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}
</script>

<template>
    <Head :title="t('pages.admin.roles.head_title')" />
    <AdminLayout :title="t('pages.admin.roles.title')" :title-icon="IconShieldLock">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/authorization/roles/create" :icon="IconShieldPlus" tone="primary">
                    {{ t('pages.admin.roles.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.roles.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect
                        v-model="filters.assignment"
                        :label="t('pages.admin.roles.filters.assignment')"
                        :options="assignmentOptions"
                    />
                    <FormSelect
                        v-model="filters.permissions"
                        :label="t('pages.admin.roles.filters.permissions')"
                        :options="permissionOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.roles.table.title')"
                :rows="roles"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.roles.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
