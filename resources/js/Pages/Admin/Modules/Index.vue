<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPuzzle } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import { moduleCategoryLabel as categoryLabel, moduleSourceLabel } from '../../../Composables/useModuleActivationUi';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll, yesNoOptionsWithAll } from '../../../Utils/filterOptions';

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
    scheduledChangesCount: number;
    requiredDependencies: string;
    optionalDependencies: string;
}

const props = defineProps<{
    modules: ModuleRow[];
    filterOptions: {
        categories: string[];
        sources: string[];
    };
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['category', 'source', 'availability', 'global', 'team', 'effective', 'globalSupport', 'teamSupport', 'scheduled'];
const filterDefaults = {
    category: 'all',
    source: 'all',
    availability: 'all',
    global: 'all',
    team: 'all',
    effective: 'all',
    globalSupport: 'all',
    teamSupport: 'all',
    scheduled: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });

const rows = computed<ModuleRow[]>(() =>
    props.modules.map((module) => ({
        ...module,
        category: moduleCategoryLabel(module.category),
        teamStateSource: sourceLabel(module.teamStateSource),
    })),
);
const columns = computed<DataTableColumn<ModuleRow>[]>(() => [
    { key: 'moduleKey', label: t('pages.admin.modules.table.module') },
    { key: 'category', label: t('pages.admin.modules.table.category') },
    { key: 'technicallyAvailable', label: t('pages.admin.modules.table.technically_available'), format: 'boolean' },
    { key: 'globallyEnabled', label: t('pages.admin.modules.table.global'), format: 'boolean' },
    { key: 'teamEnabled', label: t('pages.admin.modules.table.active_team'), format: 'boolean' },
    { key: 'effectiveEnabled', label: t('pages.admin.modules.table.effective'), format: 'boolean' },
    { key: 'teamStateSource', label: t('pages.admin.modules.table.team_source') },
    { key: 'scheduledChangesCount', label: t('pages.admin.modules.table.scheduled_changes'), format: 'number' },
    { key: 'supportsGlobalActivation', label: t('pages.admin.modules.table.global_support'), format: 'boolean', hidden: true },
    { key: 'supportsTeamActivation', label: t('pages.admin.modules.table.team_support'), format: 'boolean', hidden: true },
    { key: 'requiredDependencies', label: t('pages.admin.modules.table.required_dependencies'), format: 'list', hidden: true },
    { key: 'optionalDependencies', label: t('pages.admin.modules.table.optional_dependencies'), format: 'list', hidden: true },
]);
const actions = computed<DataTableAction<ModuleRow>[]>(() => [
    {
        key: 'show',
        label: t('pages.admin.modules.actions.show'),
        href: (module) => `/admin/modules/${encodeURIComponent(module.moduleKey)}`,
    },
]);
const categoryOptions = computed<FormSelectOption[]>(() => [
    ...optionsWithAll(props.filterOptions.categories, t('pages.admin.modules.filters.any_category'), moduleCategoryLabel),
]);
const sourceOptions = computed<FormSelectOption[]>(() => [
    ...optionsWithAll(props.filterOptions.sources, t('pages.admin.modules.filters.any_source'), sourceLabel),
]);
const booleanOptions = computed<FormSelectOption[]>(() =>
    yesNoOptionsWithAll(t('pages.admin.modules.filters.any_boolean'), t('datatable.boolean.yes'), t('datatable.boolean.no')),
);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        category: String(props.table.state.filters?.category ?? 'all'),
        source: String(props.table.state.filters?.source ?? 'all'),
        availability: String(props.table.state.filters?.availability ?? 'all'),
        global: String(props.table.state.filters?.global ?? 'all'),
        team: String(props.table.state.filters?.team ?? 'all'),
        effective: String(props.table.state.filters?.effective ?? 'all'),
        globalSupport: String(props.table.state.filters?.globalSupport ?? 'all'),
        teamSupport: String(props.table.state.filters?.teamSupport ?? 'all'),
        scheduled: String(props.table.state.filters?.scheduled ?? 'all'),
    };
}

function moduleCategoryLabel(category: string): string {
    return categoryLabel(category, t);
}

function sourceLabel(source: string): string {
    return moduleSourceLabel(source, t);
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
    <Head :title="t('pages.admin.modules.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.modules.title')" :title-icon="IconPuzzle">
        <PageStack>
            <FilterPanel
                :title="t('pages.admin.modules.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.category" :label="t('pages.admin.modules.filters.category')" :options="categoryOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.modules.filters.source')" :options="sourceOptions" />
                    <FormSelect
                        v-model="filters.availability"
                        :label="t('pages.admin.modules.filters.availability')"
                        :options="booleanOptions"
                    />
                    <FormSelect v-model="filters.effective" :label="t('pages.admin.modules.filters.effective')" :options="booleanOptions" />
                    <FormSelect v-model="filters.scheduled" :label="t('pages.admin.modules.filters.scheduled')" :options="booleanOptions" />
                    <FormSelect v-model="filters.global" :label="t('pages.admin.modules.filters.global')" :options="booleanOptions" />
                    <FormSelect v-model="filters.team" :label="t('pages.admin.modules.filters.team')" :options="booleanOptions" />
                    <FormSelect
                        v-model="filters.globalSupport"
                        :label="t('pages.admin.modules.filters.global_support')"
                        :options="booleanOptions"
                    />
                    <FormSelect
                        v-model="filters.teamSupport"
                        :label="t('pages.admin.modules.filters.team_support')"
                        :options="booleanOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.modules.table.title')"
                :rows="rows"
                :columns="columns"
                row-key="moduleKey"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.modules.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
