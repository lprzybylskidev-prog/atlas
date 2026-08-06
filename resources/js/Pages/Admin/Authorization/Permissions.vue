<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconKey } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import { optionsWithAll, yesNoOptionsWithAll } from '../../../Utils/filterOptions';
import { moduleLabel } from '../../../Utils/moduleLabels';

interface PermissionRow extends Record<string, unknown> {
    id: number | null;
    publicId: string;
    name: string;
    displayName: string;
    guard: string;
    description: string;
    module: string;
    teamScoped: boolean;
    moduleActivation: 'active' | 'inactive';
    assigned: boolean;
    effective: boolean;
    ineffectiveReason: string | null;
    createdAt: string;
    updatedAt: string;
}

const props = defineProps<{
    permissions: PermissionRow[];
    filterOptions: {
        modules: string[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['module', 'activation', 'teamScoped', 'assigned', 'effective'];
const filterDefaults = {
    module: 'all',
    activation: 'all',
    teamScoped: 'all',
    assigned: 'all',
    effective: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<PermissionRow[]>(() =>
    props.permissions.map((permission) => ({
        ...permission,
        module: moduleLabel(permission.module, t),
        ineffectiveReason: permission.ineffectiveReason === null ? null : ineffectiveReasonLabel(permission.ineffectiveReason),
    })),
);

const columns = computed<DataTableColumn<PermissionRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.permissions.table.public_id') },
    { key: 'id', label: t('pages.admin.permissions.table.internal_id'), hidden: true },
    { key: 'displayName', label: t('pages.admin.permissions.table.display_name') },
    { key: 'name', label: t('pages.admin.permissions.table.technical_name'), hidden: true },
    { key: 'guard', label: t('pages.admin.permissions.table.guard'), hidden: true },
    { key: 'description', label: t('pages.admin.permissions.table.description'), hidden: true },
    { key: 'module', label: t('pages.admin.permissions.table.module') },
    { key: 'teamScoped', label: t('pages.admin.permissions.table.team_scoped'), format: 'boolean' },
    { key: 'moduleActivation', label: t('pages.admin.permissions.table.module_activation'), format: 'status' },
    { key: 'assigned', label: t('pages.admin.permissions.table.assigned'), format: 'boolean' },
    { key: 'effective', label: t('pages.admin.permissions.table.effective'), format: 'boolean' },
    { key: 'ineffectiveReason', label: t('pages.admin.permissions.table.ineffective_reason'), hidden: true },
    { key: 'createdAt', label: t('pages.admin.permissions.table.created_at'), format: 'datetime', hidden: true },
    { key: 'updatedAt', label: t('pages.admin.permissions.table.updated_at'), format: 'datetime', hidden: true },
]);

const moduleOptions = computed<FormSelectOption[]>(() => [
    ...optionsWithAll(props.filterOptions.modules, t('pages.admin.permissions.filters.any_module'), (module) => moduleLabel(module, t)),
]);
const activationOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.permissions.filters.any_activation') },
    { value: 'active', label: t('datatable.status.active') },
    { value: 'inactive', label: t('datatable.status.inactive') },
]);
const booleanOptions = computed<FormSelectOption[]>(() =>
    yesNoOptionsWithAll(t('pages.admin.permissions.filters.any_boolean'), t('datatable.boolean.yes'), t('datatable.boolean.no')),
);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function ineffectiveReasonLabel(reason: string): string {
    const keys: Record<string, string> = {
        'authorization.active_team_invalid': 'pages.admin.permissions.ineffective_reason.active_team_invalid',
        'authorization.active_team_not_assigned': 'pages.admin.permissions.ineffective_reason.active_team_not_assigned',
        'authorization.active_team_required': 'pages.admin.permissions.ineffective_reason.active_team_required',
        'authorization.module_inactive': 'pages.admin.permissions.ineffective_reason.module_inactive',
        'authorization.permission_missing': 'pages.admin.permissions.ineffective_reason.permission_missing',
        'authorization.permission_unknown': 'pages.admin.permissions.ineffective_reason.permission_unknown',
        'authorization.user_unknown': 'pages.admin.permissions.ineffective_reason.user_unknown',
    };

    const key = keys[reason];

    return key === undefined ? reason : t(key);
}

function filterValues(): Record<string, string> {
    return {
        module: String(props.table.state.filters?.module ?? 'all'),
        activation: String(props.table.state.filters?.activation ?? 'all'),
        teamScoped: String(props.table.state.filters?.teamScoped ?? 'all'),
        assigned: String(props.table.state.filters?.assigned ?? 'all'),
        effective: String(props.table.state.filters?.effective ?? 'all'),
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
    <Head :title="t('pages.admin.permissions.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.permissions.title')" :title-icon="IconKey">
        <PageStack>
            <FilterPanel
                :title="t('pages.admin.permissions.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.module" :label="t('pages.admin.permissions.filters.module')" :options="moduleOptions" />
                    <FormSelect
                        v-model="filters.activation"
                        :label="t('pages.admin.permissions.filters.activation')"
                        :options="activationOptions"
                    />
                    <FormSelect
                        v-model="filters.teamScoped"
                        :label="t('pages.admin.permissions.filters.team_scoped')"
                        :options="booleanOptions"
                    />
                    <FormSelect
                        v-model="filters.assigned"
                        :label="t('pages.admin.permissions.filters.assigned')"
                        :options="booleanOptions"
                    />
                    <FormSelect
                        v-model="filters.effective"
                        :label="t('pages.admin.permissions.filters.effective')"
                        :options="booleanOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.permissions.table.title')"
                :rows="rows"
                :columns="columns"
                row-key="publicId"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.permissions.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
