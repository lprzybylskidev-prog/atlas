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

const { t } = useTranslator();

const filters = reactive({
    category: 'all',
    availability: 'all',
    effective: 'all',
    source: 'all',
});

const categoryOptions = computed(() => [
    { value: 'all', label: t('pages.admin.modules.all_categories') },
    ...Array.from(new Set(props.modules.map((module) => module.category)))
        .sort((left, right) => left.localeCompare(right))
        .map((category) => ({ value: category, label: category })),
]);

const sourceOptions = computed(() => [
    { value: 'all', label: t('pages.admin.modules.all_team_sources') },
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
    { key: 'moduleKey', label: t('pages.admin.modules.module') },
    { key: 'category', label: t('pages.admin.modules.category') },
    { key: 'technicallyAvailable', label: t('pages.admin.modules.available'), format: 'boolean' },
    { key: 'globallyEnabled', label: t('pages.admin.modules.global'), format: 'boolean' },
    { key: 'teamEnabled', label: t('pages.admin.modules.active_team'), format: 'boolean' },
    { key: 'effectiveEnabled', label: t('pages.admin.modules.effective'), format: 'boolean' },
    { key: 'teamStateSource', label: t('pages.admin.modules.team_source') },
    { key: 'supportsGlobalActivation', label: t('pages.admin.modules.global_support'), format: 'boolean', hidden: true },
    { key: 'supportsTeamActivation', label: t('pages.admin.modules.team_support'), format: 'boolean', hidden: true },
    { key: 'requiredDependencies', label: t('pages.admin.modules.required_dependencies'), hidden: true },
    { key: 'optionalDependencies', label: t('pages.admin.modules.optional_dependencies'), hidden: true },
];
const actions: DataTableAction<ModuleRow>[] = [
    { key: 'show', label: t('pages.admin.modules.manage_teams'), href: (row) => `/admin/modules/${row.moduleKey}` },
];
</script>

<template>
    <Head :title="t('navigation.modules')" />
    <AdminLayout :title="t('navigation.modules')" :title-icon="IconPuzzle">
        <PageStack>
            <FilterPanel
                :title="t('pages.admin.modules.filters')"
                :summary="t('pages.admin.modules.summary', { visible: filteredModules.length, total: modules.length })"
                @apply="() => {}"
                @clear="resetFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.category" :label="t('pages.admin.modules.category')" :options="categoryOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.modules.team_source')" :options="sourceOptions" />
                    <FormSelect
                        v-model="filters.availability"
                        :label="t('pages.admin.modules.available')"
                        :options="
                            booleanOptions(
                                t('pages.admin.modules.any_availability'),
                                t('pages.admin.modules.available'),
                                t('pages.admin.modules.unavailable'),
                            )
                        "
                    />
                    <FormSelect
                        v-model="filters.effective"
                        :label="t('pages.admin.modules.effective')"
                        :options="
                            booleanOptions(
                                t('pages.admin.modules.any_effective_state'),
                                t('pages.admin.modules.enabled'),
                                t('pages.admin.modules.disabled'),
                            )
                        "
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('navigation.modules')"
                :rows="filteredModules"
                :columns="columns"
                row-key="moduleKey"
                :actions="actions"
                :table="table"
                :filters="filters"
            />
        </PageStack>
    </AdminLayout>
</template>
