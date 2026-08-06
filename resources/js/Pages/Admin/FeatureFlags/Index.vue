<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconActivity, IconFlag, IconHistory, IconListDetails, IconPencil, IconRefresh, IconUsersGroup } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../../Components/Form/DialogFormActions.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';
import { moduleLabel } from '../../../Utils/moduleLabels';

interface TeamOption {
    publicId: string;
    name: string;
}

interface FeatureFlagRow extends Record<string, unknown> {
    key: string;
    name: string;
    description: string;
    type: string;
    ownerModule: string;
    lifecycle: string;
    teamScoped: boolean;
    defaultEnabled: boolean;
    globalEnabled: boolean | null;
    teamEnabled: boolean | null;
    effectiveEnabled: boolean;
    source: string;
    selectedTeamPublicId: string | null;
}

interface FeatureFlagHistoryRow extends Record<string, unknown> {
    publicId: string;
    createdAt: string | null;
    flagKey: string;
    scope: string;
    teamName: string | null;
    teamPublicId: string | null;
    action: string;
    reason: string;
    actorPublicId: string;
    beforeEnabled: boolean | null;
    afterEnabled: boolean | null;
}

interface FeatureFlagSummary {
    registered: number;
    visible: number;
    effectiveEnabled: number;
    globalValues: number;
    teamOverrides: number;
    historyRows: number;
}

type EditMode = 'global' | 'team' | 'clear-team';

const props = defineProps<{
    flags: FeatureFlagRow[];
    teams: TeamOption[];
    selectedTeamPublicId: string | null;
    summary: FeatureFlagSummary;
    filterOptions: {
        owners: string[];
        lifecycles: string[];
        teams: TeamOption[];
    };
    history: FeatureFlagHistoryRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['team', 'status', 'source', 'owner', 'lifecycle'];
const filterDefaults = {
    team: props.selectedTeamPublicId ?? 'all',
    status: 'all',
    source: 'all',
    owner: 'all',
    lifecycle: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const selectedFlag = ref<FeatureFlagRow | null>(null);
const editMode = ref<EditMode>('global');
const editModalOpen = ref(false);
const form = useForm<{
    enabled: boolean;
    reason: string;
    team_public_id: string | null;
}>({
    enabled: false,
    reason: '',
    team_public_id: props.selectedTeamPublicId,
});

const flagRows = computed<FeatureFlagRow[]>(() =>
    props.flags.map((flag) => ({
        ...flag,
        ownerModule: moduleLabel(flag.ownerModule, t),
        source: sourceLabel(flag.source),
        lifecycle: lifecycleLabel(flag.lifecycle),
        type: typeLabel(flag.type),
    })),
);
const flagColumns = computed<DataTableColumn<FeatureFlagRow>[]>(() => [
    { key: 'name', label: t('pages.admin.feature_flags.table.flag') },
    { key: 'key', label: t('pages.admin.feature_flags.table.key') },
    { key: 'ownerModule', label: t('pages.admin.feature_flags.table.owner_module') },
    { key: 'effectiveEnabled', label: t('pages.admin.feature_flags.table.effective'), format: 'boolean' },
    { key: 'source', label: t('pages.admin.feature_flags.table.source'), format: 'status' },
    { key: 'globalEnabled', label: t('pages.admin.feature_flags.table.global'), format: 'boolean' },
    { key: 'teamEnabled', label: t('pages.admin.feature_flags.table.team'), format: 'boolean' },
    { key: 'defaultEnabled', label: t('pages.admin.feature_flags.table.default'), format: 'boolean', hidden: true },
    { key: 'type', label: t('pages.admin.feature_flags.table.type'), hidden: true },
    { key: 'lifecycle', label: t('pages.admin.feature_flags.table.lifecycle') },
    { key: 'teamScoped', label: t('pages.admin.feature_flags.table.team_scoped'), format: 'boolean', hidden: true },
    { key: 'description', label: t('pages.admin.feature_flags.table.description'), hidden: true },
]);
const historyColumns = computed<DataTableColumn<FeatureFlagHistoryRow>[]>(() => [
    { key: 'createdAt', label: t('pages.admin.feature_flags.history.changed'), format: 'datetime' },
    { key: 'flagKey', label: t('pages.admin.feature_flags.history.flag') },
    { key: 'scope', label: t('pages.admin.feature_flags.history.scope'), format: 'status' },
    { key: 'teamName', label: t('pages.admin.feature_flags.history.team') },
    { key: 'action', label: t('pages.admin.feature_flags.history.action'), format: 'status' },
    { key: 'afterEnabled', label: t('pages.admin.feature_flags.history.after'), format: 'boolean' },
    { key: 'reason', label: t('pages.admin.feature_flags.history.reason') },
    { key: 'actorPublicId', label: t('pages.admin.feature_flags.history.actor'), hidden: true },
    { key: 'beforeEnabled', label: t('pages.admin.feature_flags.history.before'), format: 'boolean', hidden: true },
    { key: 'teamPublicId', label: t('pages.admin.feature_flags.history.team_public_id'), hidden: true },
]);
const flagActions = computed<DataTableAction<FeatureFlagRow>[]>(() => [
    {
        key: 'global',
        label: t('pages.admin.feature_flags.actions.set_global'),
        onAction: (flag) => openEdit(flag, 'global'),
        tone: 'info',
    },
    {
        key: 'team',
        label: t('pages.admin.feature_flags.actions.set_team'),
        onAction: (flag) => openEdit(flag, 'team'),
        tone: 'warning',
        visible: (flag) => flag.teamScoped && props.selectedTeamPublicId !== null,
    },
    {
        key: 'clear-team',
        label: t('pages.admin.feature_flags.actions.clear_team'),
        onAction: (flag) => openEdit(flag, 'clear-team'),
        tone: 'danger',
        visible: (flag) => flag.teamScoped && props.selectedTeamPublicId !== null && flag.teamEnabled !== null,
    },
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    ...props.filterOptions.teams.map((team) => ({ value: team.publicId, label: team.name })),
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.feature_flags.filters.any_status') },
    { value: 'enabled', label: t('pages.admin.feature_flags.filters.enabled') },
    { value: 'disabled', label: t('pages.admin.feature_flags.filters.disabled') },
]);
const sourceOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.feature_flags.filters.any_source') },
    { value: 'default', label: sourceLabel('default') },
    { value: 'global', label: sourceLabel('global') },
    { value: 'team', label: sourceLabel('team') },
]);
const ownerOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.owners, t('pages.admin.feature_flags.filters.any_owner'), (value) => moduleLabel(value, t)),
);
const lifecycleOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.lifecycles, t('pages.admin.feature_flags.filters.any_lifecycle'), lifecycleLabel),
);
const tableFilters = computed(() => filterValues());
const selectedTeamName = computed(
    () =>
        props.teams.find((team) => team.publicId === props.selectedTeamPublicId)?.name ??
        t('pages.admin.feature_flags.selected_team_fallback'),
);
const editTitle = computed(() => {
    if (editMode.value === 'clear-team') {
        return t('pages.admin.feature_flags.dialog.clear_title');
    }

    return t('pages.admin.feature_flags.dialog.update_title');
});
const editTarget = computed(() => {
    if (editMode.value === 'global') {
        return t('pages.admin.feature_flags.source.global');
    }

    return selectedTeamName.value;
});

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        team: String(props.table.state.filters?.team ?? props.selectedTeamPublicId ?? 'all'),
        status: String(props.table.state.filters?.status ?? 'all'),
        source: String(props.table.state.filters?.source ?? 'all'),
        owner: String(props.table.state.filters?.owner ?? 'all'),
        lifecycle: String(props.table.state.filters?.lifecycle ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults, status: 'all', source: 'all', owner: 'all', lifecycle: 'all' };
    clearTableFilters(['status', 'source', 'owner', 'lifecycle']);
}

function openEdit(flag: FeatureFlagRow, mode: EditMode): void {
    selectedFlag.value = flag;
    editMode.value = mode;
    form.defaults({
        enabled: mode === 'team' ? (flag.teamEnabled ?? flag.effectiveEnabled) : (flag.globalEnabled ?? flag.effectiveEnabled),
        reason: '',
        team_public_id: props.selectedTeamPublicId,
    });
    form.reset();
    form.clearErrors();
    editModalOpen.value = true;
}

function closeEditModal(): void {
    editModalOpen.value = false;
    selectedFlag.value = null;
    form.reset();
    form.clearErrors();
}

function submitChange(): void {
    if (selectedFlag.value === null) {
        return;
    }

    const flagKey = encodeURIComponent(selectedFlag.value.key);
    const options = {
        preserveScroll: true,
        onSuccess: closeEditModal,
    };

    if (editMode.value === 'global') {
        form.patch(`/admin/feature-flags/${flagKey}/global`, options);
        return;
    }

    if (editMode.value === 'team') {
        form.patch(`/admin/feature-flags/${flagKey}/teams`, options);
        return;
    }

    form.delete(`/admin/feature-flags/${flagKey}/teams`, options);
}

function sourceLabel(value: string): string {
    const keys: Record<string, string> = {
        default: 'pages.admin.feature_flags.source.default',
        global: 'pages.admin.feature_flags.source.global',
        team: 'pages.admin.feature_flags.source.team',
    };

    return keys[value] === undefined ? formatStatus(value) : t(keys[value]);
}

function lifecycleLabel(value: string): string {
    const keys: Record<string, string> = {
        planned: 'pages.admin.feature_flags.lifecycle.planned',
    };

    return keys[value] === undefined ? formatStatus(value) : t(keys[value]);
}

function typeLabel(value: string): string {
    const keys: Record<string, string> = {
        boolean: 'pages.admin.feature_flags.type.boolean',
    };

    return keys[value] === undefined ? formatStatus(value) : t(keys[value]);
}
</script>

<template>
    <Head :title="t('pages.admin.feature_flags.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.feature_flags.title')" :title-icon="IconFlag">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.registered')"
                    :value="summary.registered"
                    :icon="IconFlag"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.enabled_effectively')"
                    :value="summary.effectiveEnabled"
                    :icon="IconActivity"
                    :tone="summary.effectiveEnabled > 0 ? 'emerald' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.global_values')"
                    :value="summary.globalValues"
                    :icon="IconRefresh"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.team_overrides')"
                    :value="summary.teamOverrides"
                    :icon="IconUsersGroup"
                    :tone="summary.teamOverrides > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.feature_flags.metric.history_rows')"
                    :value="summary.historyRows"
                    :icon="IconHistory"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.feature_flags.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.team" :label="t('pages.admin.feature_flags.filters.team')" :options="teamOptions" />
                    <FormSelect v-model="filters.status" :label="t('pages.admin.feature_flags.filters.status')" :options="statusOptions" />
                    <FormSelect v-model="filters.source" :label="t('pages.admin.feature_flags.filters.source')" :options="sourceOptions" />
                    <FormSelect v-model="filters.owner" :label="t('pages.admin.feature_flags.filters.owner')" :options="ownerOptions" />
                    <FormSelect
                        v-model="filters.lifecycle"
                        :label="t('pages.admin.feature_flags.filters.lifecycle')"
                        :options="lifecycleOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.feature_flags.flags.title')"
                :rows="flagRows"
                :columns="flagColumns"
                row-key="key"
                :actions="flagActions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.feature_flags.flags.empty')"
            />

            <DataTable
                :title="t('pages.admin.feature_flags.history.title')"
                :rows="history"
                :columns="historyColumns"
                row-key="publicId"
                :ui-locale="locale"
                :empty-label="t('pages.admin.feature_flags.history.empty')"
            />
        </PageStack>

        <DialogPanel
            v-model:open="editModalOpen"
            :title="editTitle"
            :icon="IconPencil"
            :tone="editMode === 'clear-team' ? 'rose' : 'amber'"
            :close-label="t('modal.cancel')"
            @close="closeEditModal"
        >
            <AtlasForm :processing="form.processing" @submit="submitChange">
                <div class="space-y-4">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ selectedFlag?.name }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ t('pages.admin.feature_flags.dialog.target', { target: editTarget }) }}
                        </p>
                    </div>
                    <FormCheckbox
                        v-if="editMode !== 'clear-team'"
                        v-model="form.enabled"
                        :label="t('pages.admin.feature_flags.dialog.enabled')"
                    />
                    <FormTextarea
                        v-model="form.reason"
                        :label="t('pages.admin.feature_flags.dialog.reason')"
                        :placeholder="t('pages.admin.feature_flags.dialog.reason_placeholder')"
                        :error="form.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="
                        editMode === 'clear-team'
                            ? t('pages.admin.feature_flags.actions.clear_team')
                            : t('pages.admin.feature_flags.actions.save')
                    "
                    :submit-icon="IconPencil"
                    :submit-tone="editMode === 'clear-team' ? 'danger' : 'primary'"
                    :loading="form.processing"
                    @cancel="closeEditModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
