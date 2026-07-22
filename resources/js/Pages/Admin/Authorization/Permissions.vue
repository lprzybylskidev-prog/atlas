<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';
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

const props = defineProps<{
    permissions: PermissionRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

const filters = reactive({
    module: 'all',
    teamScoped: 'all',
    assigned: 'all',
    effective: 'all',
    activation: 'all',
});

const moduleOptions = computed(() => [
    { value: 'all', label: 'All modules' },
    ...Array.from(new Set(props.permissions.map((permission) => permission.module)))
        .sort((left, right) => left.localeCompare(right))
        .map((module) => ({ value: module, label: module })),
]);

const booleanOptions = (allLabel: string, yesLabel: string, noLabel: string) => [
    { value: 'all', label: allLabel },
    { value: 'yes', label: yesLabel },
    { value: 'no', label: noLabel },
];

const activationOptions = computed(() => [
    { value: 'all', label: 'All module states' },
    ...Array.from(new Set(props.permissions.map((permission) => permission.moduleActivation)))
        .sort((left, right) => left.localeCompare(right))
        .map((activation) => ({ value: activation, label: activation })),
]);

const filteredPermissions = computed(() =>
    props.permissions.filter((permission) => {
        if (filters.module !== 'all' && permission.module !== filters.module) {
            return false;
        }

        if (filters.activation !== 'all' && permission.moduleActivation !== filters.activation) {
            return false;
        }

        if (filters.teamScoped !== 'all' && permission.teamScoped !== (filters.teamScoped === 'yes')) {
            return false;
        }

        if (filters.assigned !== 'all' && permission.assigned !== (filters.assigned === 'yes')) {
            return false;
        }

        return filters.effective === 'all' || permission.effective === (filters.effective === 'yes');
    }),
);

function resetFilters(): void {
    filters.module = 'all';
    filters.teamScoped = 'all';
    filters.assigned = 'all';
    filters.effective = 'all';
    filters.activation = 'all';
}

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
        <PageStack>
            <FilterPanel
                title="Permission filters"
                :summary="`Showing ${filteredPermissions.length} of ${permissions.length} loaded permissions.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.module" label="Module" :options="moduleOptions" />
                    <FormSelect v-model="filters.activation" label="Module state" :options="activationOptions" />
                    <FormSelect
                        v-model="filters.teamScoped"
                        label="Team scoped"
                        :options="booleanOptions('Any scope', 'Team scoped', 'Not team scoped')"
                    />
                    <FormSelect
                        v-model="filters.assigned"
                        label="Assigned"
                        :options="booleanOptions('Any assignment', 'Assigned', 'Not assigned')"
                    />
                    <FormSelect
                        v-model="filters.effective"
                        label="Effective"
                        :options="booleanOptions('Any effective state', 'Effective', 'Ineffective')"
                    />
                </div>
            </FilterPanel>

            <DataTable
                title="Permissions"
                :rows="filteredPermissions"
                :columns="columns"
                row-key="publicId"
                :table="table"
                ui-locale="en"
            />
        </PageStack>
    </AdminLayout>
</template>
