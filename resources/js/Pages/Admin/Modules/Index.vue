<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPuzzle } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
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

const props = defineProps<{
    modules: ModuleRow[];
    table: DataTableMeta;
}>();

const { t } = useTranslator('en');

const filters = reactive({
    category: 'all',
    availability: 'all',
    effective: 'all',
    source: 'all',
});

const categoryOptions = computed(() => [
    { value: 'all', label: 'All categories' },
    ...Array.from(new Set(props.modules.map((module) => module.category)))
        .sort((left, right) => left.localeCompare(right))
        .map((category) => ({ value: category, label: category })),
]);

const sourceOptions = computed(() => [
    { value: 'all', label: 'All team sources' },
    ...Array.from(new Set(props.modules.map((module) => module.teamStateSource)))
        .sort((left, right) => left.localeCompare(right))
        .map((source) => ({ value: source, label: source })),
]);

const booleanOptions = (allLabel: string, yesLabel: string, noLabel: string) => [
    { value: 'all', label: allLabel },
    { value: 'yes', label: yesLabel },
    { value: 'no', label: noLabel },
];

const filteredModules = computed(() =>
    props.modules.filter((module) => {
        if (filters.category !== 'all' && module.category !== filters.category) {
            return false;
        }

        if (filters.source !== 'all' && module.teamStateSource !== filters.source) {
            return false;
        }

        if (filters.availability !== 'all' && module.technicallyAvailable !== (filters.availability === 'yes')) {
            return false;
        }

        return filters.effective === 'all' || module.effectiveEnabled === (filters.effective === 'yes');
    }),
);

function resetFilters(): void {
    filters.category = 'all';
    filters.availability = 'all';
    filters.effective = 'all';
    filters.source = 'all';
}

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
        <PageStack>
            <FilterPanel
                title="Module filters"
                :summary="`Showing ${filteredModules.length} of ${modules.length} loaded modules.`"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.category" label="Category" :options="categoryOptions" />
                    <FormSelect v-model="filters.source" label="Team source" :options="sourceOptions" />
                    <FormSelect
                        v-model="filters.availability"
                        label="Available"
                        :options="booleanOptions('Any availability', 'Available', 'Unavailable')"
                    />
                    <FormSelect
                        v-model="filters.effective"
                        label="Effective"
                        :options="booleanOptions('Any effective state', 'Enabled', 'Disabled')"
                    />
                </div>
            </FilterPanel>

            <DataTable
                title="Modules"
                :rows="filteredModules"
                :columns="columns"
                row-key="moduleKey"
                :actions="actions"
                :table="table"
                ui-locale="en"
            />
        </PageStack>
    </AdminLayout>
</template>
