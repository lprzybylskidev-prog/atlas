<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPackage, IconPackageExport } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';

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
    filterOptions: {
        teams: FormSelectOption[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['status', 'team', 'roles', 'directPermissions', 'templatePermissions'];
const filterDefaults = {
    status: 'all',
    team: 'all',
    roles: 'all',
    directPermissions: 'all',
    templatePermissions: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const columns = computed<DataTableColumn<PackageRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.packages.table.public_id') },
    { key: 'id', label: t('pages.admin.packages.table.internal_id'), hidden: true },
    { key: 'teamName', label: t('pages.admin.packages.table.team') },
    { key: 'teamPublicId', label: t('pages.admin.packages.table.team_public_id'), hidden: true },
    { key: 'label', label: t('pages.admin.packages.table.display_name') },
    { key: 'name', label: t('pages.admin.packages.table.technical_name'), hidden: true },
    { key: 'initialRoles', label: t('pages.admin.packages.table.initial_roles'), format: 'list' },
    { key: 'directPermissions', label: t('pages.admin.packages.table.direct_permissions'), format: 'list', hidden: true },
    { key: 'templatePermissions', label: t('pages.admin.packages.table.template_permissions'), format: 'list', hidden: true },
    { key: 'isActive', label: t('pages.admin.packages.table.active'), format: 'boolean', hidden: true },
    { key: 'createdAt', label: t('pages.admin.packages.table.created_at'), format: 'datetime', hidden: true },
    { key: 'updatedAt', label: t('pages.admin.packages.table.updated_at'), format: 'datetime', hidden: true },
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.packages.filters.any_status') },
    { value: 'active', label: t('datatable.status.active') },
    { value: 'inactive', label: t('datatable.status.inactive') },
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.packages.filters.any_team') },
    ...props.filterOptions.teams,
]);
const assignmentOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.packages.filters.any_assignment') },
    { value: 'with', label: t('pages.admin.packages.filters.with_assignment') },
    { value: 'without', label: t('pages.admin.packages.filters.without_assignment') },
]);

const actions = computed<DataTableAction<PackageRow>[]>(() => [
    {
        key: 'edit',
        label: t('pages.admin.packages.actions.edit'),
        href: (preset) => `/admin/authorization/packages/${encodeURIComponent(preset.publicId)}/edit`,
    },
    {
        key: 'delete',
        label: t('pages.admin.packages.actions.deactivate'),
        method: 'delete',
        href: (preset) => `/admin/authorization/packages/${encodeURIComponent(preset.publicId)}`,
        confirm: (preset) => t('pages.admin.packages.actions.deactivate_confirm', { preset: preset.label }),
        disabled: (preset) => !preset.isActive,
        disabledReason: () => t('pages.admin.packages.actions.deactivate_disabled'),
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
        status: String(props.table.state.filters?.status ?? 'all'),
        team: String(props.table.state.filters?.team ?? 'all'),
        roles: String(props.table.state.filters?.roles ?? 'all'),
        directPermissions: String(props.table.state.filters?.directPermissions ?? 'all'),
        templatePermissions: String(props.table.state.filters?.templatePermissions ?? 'all'),
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
    <Head :title="t('pages.admin.packages.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.packages.title')" :title-icon="IconPackage">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/authorization/packages/create" :icon="IconPackageExport" tone="primary">
                    {{ t('pages.admin.packages.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.packages.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.status" :label="t('pages.admin.packages.filters.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.team" :label="t('pages.admin.packages.filters.team')" :options="teamOptions" />
                    <FormSelect v-model="filters.roles" :label="t('pages.admin.packages.filters.roles')" :options="assignmentOptions" />
                    <FormSelect
                        v-model="filters.directPermissions"
                        :label="t('pages.admin.packages.filters.direct_permissions')"
                        :options="assignmentOptions"
                    />
                    <FormSelect
                        v-model="filters.templatePermissions"
                        :label="t('pages.admin.packages.filters.template_permissions')"
                        :options="assignmentOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.packages.table.title')"
                :rows="packages"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.packages.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
