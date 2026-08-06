<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconBriefcase, IconClockHour4, IconDatabase, IconFilePencil, IconPlayerPause, IconPlus } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import DataTable from '../../Components/DataTable.vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../Components/Form/DialogFormActions.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../Composables/useTableFilterControls';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableColumn } from '../../Types/data-table';
import type { ShellSubnavigationItem } from '../../Types/navigation';

interface CategoryRow extends Record<string, unknown> {
    publicId: string;
    teamPublicId: string;
    teamName: string;
    key: string;
    labelPl: string;
    labelEn: string;
    descriptionPl: string;
    descriptionEn: string;
    requiresComment: boolean;
    autoApprovalEnabled: boolean;
    isActive: boolean;
}

interface TeamOption {
    publicId: string;
    name: string;
    trackedUsers: number;
}

const props = defineProps<{
    surface?: 'admin' | 'manager';
    categories: CategoryRow[];
    teamOptions: TeamOption[];
    filters: Record<string, string>;
}>();

const { locale, t } = useTranslator();
const surface = computed(() => props.surface ?? 'admin');
const isManagerSurface = computed(() => surface.value === 'manager');
const filterKeys = ['team', 'status'];
const filterDefaults = { team: '', status: 'all' };
const filters = ref({ ...filterDefaults, ...props.filters });
const selectedCategory = ref<CategoryRow | null>(null);
const deactivateForm = useForm({
    team_public_id: '',
    reason: '',
});

const subnavigation = computed<ShellSubnavigationItem[]>(() => [
    {
        key: 'daily',
        label: t('navigation.work_time_daily'),
        href: '/admin/work-time/summary',
        icon: IconClockHour4,
        active: false,
    },
    {
        key: 'other_work',
        label: t('navigation.work_time_other_work'),
        href: '/admin/work-time/other-work',
        icon: IconBriefcase,
        active: true,
    },
    {
        key: 'breaks',
        label: t('navigation.work_time_breaks'),
        href: '/admin/work-time/breaks',
        icon: IconPlayerPause,
        active: false,
    },
    {
        key: 'corrections',
        label: t('navigation.work_time_corrections'),
        href: '/admin/work-time/corrections',
        icon: IconFilePencil,
        active: false,
    },
    {
        key: 'work_sessions',
        label: t('navigation.work_time_sessions'),
        href: '/admin/work-time/work-sessions',
        icon: IconDatabase,
        active: false,
    },
]);
const columns = computed<DataTableColumn<CategoryRow>[]>(() => [
    { key: 'teamName', label: t('pages.time_tracking.admin_categories.table.team') },
    { key: 'key', label: t('pages.time_tracking.admin_categories.table.key') },
    { key: 'labelPl', label: t('pages.time_tracking.admin_categories.table.label_pl') },
    { key: 'labelEn', label: t('pages.time_tracking.admin_categories.table.label_en') },
    { key: 'requiresComment', label: t('pages.time_tracking.admin_categories.table.requires_comment'), format: 'boolean' },
    { key: 'autoApprovalEnabled', label: t('pages.time_tracking.admin_categories.table.auto_approval'), format: 'boolean' },
    { key: 'isActive', label: t('pages.time_tracking.admin_categories.table.active'), format: 'boolean' },
    { key: 'descriptionPl', label: t('pages.time_tracking.admin_categories.table.description_pl'), hidden: true },
    { key: 'descriptionEn', label: t('pages.time_tracking.admin_categories.table.description_en'), hidden: true },
]);
const actions = computed<DataTableAction<CategoryRow>[]>(() => [
    {
        key: 'deactivate',
        label: t('pages.time_tracking.admin_categories.actions.deactivate'),
        tone: 'danger',
        visible: (category) => category.isActive,
        onAction: openDeactivateDialog,
    },
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.team_placeholder') },
    ...props.teamOptions.map((team) => ({
        value: team.publicId,
        label: t('pages.time_tracking.admin_operations.filters.team_option', { team: team.name, count: team.trackedUsers }),
    })),
]);
const statusOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.time_tracking.admin_categories.filters.any_status') },
    { value: 'active', label: t('datatable.status.active') },
    { value: 'inactive', label: t('datatable.status.inactive') },
]);
const tableFilters = computed(() => ({ ...filters.value }));

watch(
    () => props.filters,
    () => {
        filters.value = { ...filterDefaults, ...props.filters };
    },
);

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function openDeactivateDialog(category: CategoryRow): void {
    selectedCategory.value = category;
    deactivateForm.defaults({
        team_public_id: category.teamPublicId,
        reason: '',
    });
    deactivateForm.reset();
    deactivateForm.clearErrors();
}

function closeDeactivateDialog(): void {
    selectedCategory.value = null;
    deactivateForm.reset();
    deactivateForm.clearErrors();
}

function deactivateCategory(): void {
    if (selectedCategory.value === null) {
        return;
    }

    deactivateForm.delete(`${basePath()}/other-work/categories/${encodeURIComponent(selectedCategory.value.key)}`, {
        preserveScroll: true,
        onSuccess: closeDeactivateDialog,
    });
}

function basePath(): string {
    return isManagerSurface.value ? '/manager/work-time' : '/admin/work-time';
}
</script>

<template>
    <Head :title="t('pages.time_tracking.admin_categories.head_title')" />
    <AppLayout
        :mode="surface"
        :title="t('pages.time_tracking.admin_categories.title')"
        :title-icon="IconBriefcase"
        :subnavigation="isManagerSurface ? [] : subnavigation"
        :subnavigation-label="t('navigation.group.work_time')"
    >
        <PageStack>
            <div class="flex flex-wrap justify-between gap-3">
                <ActionLink :href="`${basePath()}/other-work`" :icon="IconArrowLeft">
                    {{ t('pages.time_tracking.admin_categories.actions.back_to_other_work') }}
                </ActionLink>
                <ActionLink :href="`${basePath()}/other-work/categories/create`" :icon="IconPlus" tone="primary">
                    {{ t('pages.time_tracking.admin_categories.actions.create') }}
                </ActionLink>
            </div>

            <FilterPanel
                :title="t('pages.time_tracking.admin_categories.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect
                        v-model="filters.team"
                        :label="t('pages.time_tracking.admin_operations.filters.team')"
                        :options="teamOptions"
                    />
                    <FormSelect
                        v-model="filters.status"
                        :label="t('pages.time_tracking.admin_categories.filters.status')"
                        :options="statusOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.time_tracking.admin_categories.table.title')"
                :rows="categories"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.time_tracking.admin_categories.table.empty')"
            />
        </PageStack>

        <DialogPanel
            :open="selectedCategory !== null"
            :title="t('pages.time_tracking.admin_categories.deactivate.title')"
            :icon="IconBriefcase"
            tone="rose"
            :close-label="t('modal.cancel')"
            @update:open="(open) => (open ? undefined : closeDeactivateDialog())"
            @close="closeDeactivateDialog"
        >
            <AtlasForm :processing="deactivateForm.processing" @submit="deactivateCategory">
                <div class="space-y-4">
                    <div v-if="selectedCategory" class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                        {{ selectedCategory.labelPl }} / {{ selectedCategory.labelEn }}
                    </div>
                    <FormTextarea
                        v-model="deactivateForm.reason"
                        :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                        :error="deactivateForm.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.time_tracking.admin_categories.actions.deactivate')"
                    :submit-icon="IconBriefcase"
                    submit-tone="danger"
                    :loading="deactivateForm.processing"
                    @cancel="closeDeactivateDialog"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
