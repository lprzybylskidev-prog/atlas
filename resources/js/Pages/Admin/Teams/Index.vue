<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconUsersGroup, IconUserPlus } from '@tabler/icons-vue';
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

interface TeamRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    name: string;
    displayName: string;
    isActive: boolean;
    membersCount: number;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    teams: TeamRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['status', 'members'];
const filterDefaults = { status: 'all', members: 'all' };
const filters = ref({ ...filterDefaults, ...filterValues() });

const columns = computed<DataTableColumn<TeamRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.teams.table.public_id') },
    { key: 'id', label: t('pages.admin.teams.table.internal_id'), hidden: true },
    { key: 'displayName', label: t('pages.admin.teams.table.display_name') },
    { key: 'name', label: t('pages.admin.teams.table.technical_name'), hidden: true },
    { key: 'isActive', label: t('pages.admin.teams.table.active'), format: 'boolean' },
    { key: 'membersCount', label: t('pages.admin.teams.table.members_count'), format: 'number' },
    { key: 'createdAt', label: t('pages.admin.teams.table.created_at'), format: 'datetime', hidden: true },
    { key: 'updatedAt', label: t('pages.admin.teams.table.updated_at'), format: 'datetime', hidden: true },
]);
const actions = computed<DataTableAction<TeamRow>[]>(() => [
    {
        key: 'edit',
        label: t('pages.admin.teams.actions.edit'),
        href: (team) => `/admin/teams/${encodeURIComponent(team.publicId)}/edit`,
    },
    {
        key: 'activate',
        label: t('pages.admin.teams.actions.activate'),
        method: 'post',
        href: (team) => `/admin/teams/${encodeURIComponent(team.publicId)}/activate`,
        disabled: (team) => team.isActive,
        disabledReason: () => t('pages.admin.teams.actions.activate_disabled'),
        tone: 'success',
    },
    {
        key: 'deactivate',
        label: t('pages.admin.teams.actions.deactivate'),
        method: 'post',
        href: (team) => `/admin/teams/${encodeURIComponent(team.publicId)}/deactivate`,
        confirm: (team) => t('pages.admin.teams.actions.deactivate_confirm', { team: team.displayName }),
        disabled: (team) => !team.isActive,
        disabledReason: () => t('pages.admin.teams.actions.deactivate_disabled'),
        tone: 'danger',
    },
    {
        key: 'delete',
        label: t('pages.admin.teams.actions.delete'),
        method: 'delete',
        href: (team) => `/admin/teams/${encodeURIComponent(team.publicId)}`,
        confirm: (team) => t('pages.admin.teams.actions.delete_confirm', { team: team.displayName }),
        disabled: (team) => team.membersCount > 0,
        disabledReason: (team) => t('pages.admin.teams.actions.delete_disabled_members', { count: team.membersCount }),
        tone: 'danger',
    },
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.teams.filters.any_status') },
    { value: 'active', label: t('datatable.status.active') },
    { value: 'inactive', label: t('datatable.status.inactive') },
]);
const memberOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.teams.filters.any_members') },
    { value: 'with', label: t('pages.admin.teams.filters.with_members') },
    { value: 'without', label: t('pages.admin.teams.filters.without_members') },
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
        members: String(props.table.state.filters?.members ?? 'all'),
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
    <Head :title="t('pages.admin.teams.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.title')" :title-icon="IconUsersGroup">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink href="/admin/teams/create" :icon="IconUserPlus" tone="primary">
                    {{ t('pages.admin.teams.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.teams.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.status" :label="t('pages.admin.teams.filters.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.members" :label="t('pages.admin.teams.filters.members')" :options="memberOptions" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.teams.table.title')"
                :rows="teams"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.teams.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
