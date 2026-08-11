<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    IconActivity,
    IconDatabaseSearch,
    IconListDetails,
    IconPlayerPlay,
    IconRefresh,
    IconSearch,
    IconShieldLock,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../../Components/Form/DialogFormActions.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { statusTranslationKey } from '../../../Services/statusCatalog';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';
import { moduleLabel } from '../../../Utils/moduleLabels';

interface SearchIndexRow extends Record<string, unknown> {
    key: string;
    moduleKey: string;
    moduleLabel?: string;
    stableAlias: string;
    searchableFields: string[];
    filterableFields: string[];
    sortableFields: string[];
    containsSensitiveData: boolean;
    supportsDeletion: boolean;
    supportsAnonymization: boolean;
}

interface RebuildRunRow extends Record<string, unknown> {
    publicId: string;
    status: string | null;
    currentStage: string | null;
    progressCurrent: number;
    progressTotal: number | null;
    progressLabel: string | null;
    createdAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
}

interface SearchSummary {
    indexes: number;
    sensitive: number;
    recentRebuilds: number;
    activeRebuilds: number;
    visibleIndexes: number;
}

interface SearchReadiness {
    key: string;
    label: string;
    status: string;
    blocking: boolean;
    metadata: {
        configured: boolean;
        critical: boolean;
    };
}

const props = defineProps<{
    indexes: SearchIndexRow[];
    summary: SearchSummary;
    filterOptions: {
        modules: string[];
    };
    readiness: SearchReadiness;
    recentRebuilds: RebuildRunRow[];
    rebuildConfirmation: string;
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['module', 'sensitivity', 'deletion', 'anonymization'];
const filterDefaults = {
    module: 'all',
    sensitivity: 'all',
    deletion: 'all',
    anonymization: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const selectedIndex = ref<SearchIndexRow | null>(null);
const rebuildModalOpen = ref(false);
const rebuildForm = useForm<{
    confirmation: string;
    module_key: string;
    index_key: string;
}>({
    confirmation: '',
    module_key: '',
    index_key: '',
});

const columns = computed<DataTableColumn<SearchIndexRow>[]>(() => [
    { key: 'key', label: t('pages.admin.search.table.index_key') },
    { key: 'moduleLabel', label: t('pages.admin.search.table.module') },
    { key: 'stableAlias', label: t('pages.admin.search.table.stable_alias') },
    { key: 'searchableFields', label: t('pages.admin.search.table.searchable'), format: 'list' },
    { key: 'filterableFields', label: t('pages.admin.search.table.filterable'), format: 'list', hidden: true },
    { key: 'sortableFields', label: t('pages.admin.search.table.sortable'), format: 'list', hidden: true },
    { key: 'containsSensitiveData', label: t('pages.admin.search.table.sensitive'), format: 'boolean' },
    { key: 'supportsDeletion', label: t('pages.admin.search.table.deletion'), format: 'boolean' },
    { key: 'supportsAnonymization', label: t('pages.admin.search.table.anonymization'), format: 'boolean' },
]);
const actions = computed<DataTableAction<SearchIndexRow>[]>(() => [
    {
        key: 'rebuild',
        label: t('pages.admin.search.actions.rebuild_index'),
        onAction: openIndexRebuild,
        tone: 'warning',
    },
]);
const moduleOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.modules, t('pages.admin.search.filters.any_module'), (module) => moduleLabel(module, t)),
);
const sensitivityOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.search.filters.any_sensitivity') },
    { value: 'sensitive', label: t('pages.admin.search.filters.sensitive') },
    { value: 'non_sensitive', label: t('pages.admin.search.filters.non_sensitive') },
]);
const supportOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.search.filters.any_support') },
    { value: 'supported', label: t('pages.admin.search.filters.supported') },
    { value: 'unsupported', label: t('pages.admin.search.filters.unsupported') },
]);
const tableFilters = computed(() => filterValues());
const rows = computed<SearchIndexRow[]>(() =>
    props.indexes.map((index) => ({
        ...index,
        moduleLabel: moduleLabel(index.moduleKey, t),
    })),
);
const rebuildColumns = computed<DataTableColumn<RebuildRunRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.search.table.run') },
    { key: 'status', label: t('pages.admin.search.table.status'), format: 'status-badge' },
    { key: 'currentStage', label: t('pages.admin.search.table.stage') },
    { key: 'progressLabel', label: t('pages.admin.search.table.progress') },
    { key: 'createdAt', label: t('pages.admin.search.table.created'), format: 'datetime' },
]);
const rebuildRows = computed<RebuildRunRow[]>(() => props.recentRebuilds.map((run) => ({ ...run, progressLabel: progressLabel(run) })));
const rebuildActions = computed<DataTableAction<RebuildRunRow>[]>(() => [
    {
        key: 'show',
        label: t('pages.admin.search.actions.show_run'),
        href: (run) => `/admin/managed-processes/${encodeURIComponent(run.publicId)}`,
    },
]);
const readinessTone = computed(() => {
    if (props.readiness.status === 'healthy') {
        return 'emerald';
    }

    return props.readiness.status === 'unhealthy' ? 'rose' : 'amber';
});
const readinessStatusLabel = computed(() => statusLabel(props.readiness.status));
const selectedScopeLabel = computed(() => selectedIndex.value?.key ?? t('pages.admin.search.actions.rebuild_all'));

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        module: String(props.table.state.filters?.module ?? 'all'),
        sensitivity: String(props.table.state.filters?.sensitivity ?? 'all'),
        deletion: String(props.table.state.filters?.deletion ?? 'all'),
        anonymization: String(props.table.state.filters?.anonymization ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function openGlobalRebuild(): void {
    selectedIndex.value = null;
    rebuildForm.defaults({
        confirmation: '',
        module_key: '',
        index_key: '',
    });
    rebuildForm.reset();
    rebuildForm.clearErrors();
    rebuildModalOpen.value = true;
}

function openIndexRebuild(index: SearchIndexRow): void {
    selectedIndex.value = index;
    rebuildForm.defaults({
        confirmation: '',
        module_key: index.moduleKey,
        index_key: index.key,
    });
    rebuildForm.reset();
    rebuildForm.clearErrors();
    rebuildModalOpen.value = true;
}

function closeRebuildModal(): void {
    rebuildModalOpen.value = false;
    selectedIndex.value = null;
    rebuildForm.reset();
    rebuildForm.clearErrors();
}

function startRebuild(): void {
    rebuildForm.post('/admin/search/rebuild', {
        preserveScroll: true,
        onSuccess: closeRebuildModal,
    });
}

function statusLabel(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const key = statusTranslationKey(value);

    return key === undefined ? formatStatus(value) : t(key);
}

function progressLabel(run: RebuildRunRow): string {
    if (run.progressTotal === null || run.progressTotal <= 0) {
        return String(run.progressCurrent);
    }

    return `${run.progressCurrent} / ${run.progressTotal}`;
}
</script>

<template>
    <Head :title="t('pages.admin.search.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.search.title')" :title-icon="IconSearch">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <OperationalMetricTile
                    :label="t('pages.admin.search.metric.registered_indexes')"
                    :value="summary.indexes"
                    :icon="IconDatabaseSearch"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.search.metric.visible_indexes')"
                    :value="summary.visibleIndexes"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.search.metric.sensitive_indexes')"
                    :value="summary.sensitive"
                    :icon="IconShieldLock"
                    :tone="summary.sensitive > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.search.metric.active_rebuilds')"
                    :value="summary.activeRebuilds"
                    :icon="IconRefresh"
                    :tone="summary.activeRebuilds > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.search.metric.recent_rebuilds')"
                    :value="summary.recentRebuilds"
                    :icon="IconActivity"
                    tone="emerald"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.search.readiness.title')" :icon="IconDatabaseSearch" :tone="readinessTone">
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ readiness.label }}</span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="{
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200':
                                        readiness.status === 'healthy',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200': readiness.status === 'degraded',
                                    'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200': readiness.status === 'unhealthy',
                                }"
                            >
                                {{ readinessStatusLabel }}
                            </span>
                        </div>
                    </div>
                    <FormButton type="button" :icon="IconPlayerPlay" :disabled="rebuildForm.processing" @click="openGlobalRebuild">
                        {{ t('pages.admin.search.actions.rebuild_all') }}
                    </FormButton>
                </div>
            </SurfaceCard>

            <FilterPanel
                :title="t('pages.admin.search.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.module" :label="t('pages.admin.search.filters.module')" :options="moduleOptions" />
                    <FormSelect
                        v-model="filters.sensitivity"
                        :label="t('pages.admin.search.filters.sensitivity')"
                        :options="sensitivityOptions"
                    />
                    <FormSelect v-model="filters.deletion" :label="t('pages.admin.search.filters.deletion')" :options="supportOptions" />
                    <FormSelect
                        v-model="filters.anonymization"
                        :label="t('pages.admin.search.filters.anonymization')"
                        :options="supportOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.search.indexes.title')"
                :rows="rows"
                :columns="columns"
                row-key="key"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.search.indexes.empty')"
            />

            <DataTable
                :title="t('pages.admin.search.rebuilds.title')"
                :rows="rebuildRows"
                :columns="rebuildColumns"
                row-key="publicId"
                :actions="rebuildActions"
                :ui-locale="locale"
                :empty-label="t('pages.admin.search.rebuilds.empty')"
            />
        </PageStack>

        <DialogPanel
            v-model:open="rebuildModalOpen"
            :title="t('pages.admin.search.rebuild_modal.title')"
            :icon="IconPlayerPlay"
            tone="amber"
            :close-label="t('modal.cancel')"
            @close="closeRebuildModal"
        >
            <AtlasForm :processing="rebuildForm.processing" @submit="startRebuild">
                <div class="space-y-4">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ t('pages.admin.search.rebuild_modal.scope', { scope: selectedScopeLabel }) }}
                    </p>
                    <FormInput
                        v-model="rebuildForm.confirmation"
                        :label="t('pages.admin.search.rebuild_modal.confirmation')"
                        :placeholder="rebuildConfirmation"
                        :error="rebuildForm.errors.confirmation"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.search.actions.start_rebuild')"
                    :submit-icon="IconPlayerPlay"
                    :loading="rebuildForm.processing"
                    @cancel="closeRebuildModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
