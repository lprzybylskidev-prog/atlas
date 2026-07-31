<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconSitemap, IconUserPlus } from '@tabler/icons-vue';
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

interface Option {
    value: string;
    label: string;
}

interface ManagerRow extends Record<string, unknown> {
    userPublicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    email: string;
    managerType: string;
    directReportsCount: number;
    subtreeReportsCount: number;
}

const props = defineProps<{
    selectedTeamPublicId: string;
    teamOptions: Option[];
    managers: ManagerRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['team', 'type', 'directReports', 'subtreeReports'];
const filterDefaults = {
    team: props.selectedTeamPublicId,
    type: 'all',
    directReports: 'all',
    subtreeReports: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const teamSelectOptions = computed<FormSelectOption[]>(() => props.teamOptions);
const typeOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.managers.filters.any_type') },
    { value: 'head', label: t('pages.admin.managers.tree.head_manager') },
    { value: 'regular', label: t('pages.admin.managers.tree.manager') },
]);
const reportsOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.managers.filters.any_reports') },
    { value: 'with', label: t('pages.admin.managers.filters.with_reports') },
    { value: 'without', label: t('pages.admin.managers.filters.without_reports') },
]);
const rows = computed<ManagerRow[]>(() =>
    props.managers.map((manager) => ({
        ...manager,
        managerType: manager.managerType === 'head' ? t('pages.admin.managers.tree.head_manager') : t('pages.admin.managers.tree.manager'),
    })),
);
const columns = computed<DataTableColumn<ManagerRow>[]>(() => [
    { key: 'userPublicId', label: t('pages.admin.managers.table.user_public_id'), hidden: true },
    { key: 'teamPublicId', label: t('pages.admin.managers.table.team_public_id'), hidden: true },
    { key: 'teamName', label: t('pages.admin.managers.table.team') },
    { key: 'name', label: t('pages.admin.managers.table.manager') },
    { key: 'email', label: t('pages.admin.managers.table.manager_email'), hidden: true },
    { key: 'managerType', label: t('pages.admin.managers.table.manager_type') },
    { key: 'directReportsCount', label: t('pages.admin.managers.table.direct_reports_count'), format: 'number' },
    { key: 'subtreeReportsCount', label: t('pages.admin.managers.table.subtree_reports_count'), format: 'number' },
]);
const actions = computed<DataTableAction<ManagerRow>[]>(() => [
    {
        key: 'edit',
        label: t('pages.admin.managers.actions.edit'),
        href: (manager) =>
            `/admin/managers/${encodeURIComponent(manager.userPublicId)}/edit?team=${encodeURIComponent(manager.teamPublicId)}`,
    },
]);
const tableFilters = computed(() => filterValues());
const createHref = computed(() =>
    filters.value.team === '' ? '/admin/managers/create' : `/admin/managers/create?team=${encodeURIComponent(filters.value.team)}`,
);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        team: String(props.table.state.filters?.team ?? props.selectedTeamPublicId),
        type: String(props.table.state.filters?.type ?? 'all'),
        directReports: String(props.table.state.filters?.directReports ?? 'all'),
        subtreeReports: String(props.table.state.filters?.subtreeReports ?? 'all'),
    };
}

function applyFilters(): void {
    if (filters.value.team === '') {
        router.get('/admin/managers', {}, { preserveScroll: true, preserveState: false });
        return;
    }

    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}
</script>

<template>
    <Head :title="t('pages.admin.managers.head_title')" />
    <AdminLayout :title="t('pages.admin.managers.title')" :title-icon="IconSitemap">
        <PageStack>
            <div class="flex justify-end">
                <ActionLink :href="createHref" :icon="IconUserPlus" tone="primary">
                    {{ t('pages.admin.managers.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.admin.managers.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect
                        v-model="filters.team"
                        :label="t('pages.admin.managers.filters.team')"
                        :options="teamSelectOptions"
                        :placeholder="t('pages.admin.managers.filters.team_placeholder')"
                    />
                    <FormSelect v-model="filters.type" :label="t('pages.admin.managers.filters.type')" :options="typeOptions" />
                    <FormSelect
                        v-model="filters.directReports"
                        :label="t('pages.admin.managers.filters.direct_reports')"
                        :options="reportsOptions"
                    />
                    <FormSelect
                        v-model="filters.subtreeReports"
                        :label="t('pages.admin.managers.filters.subtree_reports')"
                        :options="reportsOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.managers.table.index_title')"
                :rows="rows"
                :columns="columns"
                row-key="userPublicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.managers.empty.index')"
            />
        </PageStack>
    </AdminLayout>
</template>
