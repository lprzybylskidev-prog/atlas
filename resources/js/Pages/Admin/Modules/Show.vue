<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconCalendarStats,
    IconCalendarTime,
    IconCircleCheck,
    IconCircleX,
    IconDeviceFloppy,
    IconGitBranch,
    IconPackage,
    IconPackageOff,
    IconPuzzle,
    IconShieldCheck,
    IconWorld,
    IconX,
} from '@tabler/icons-vue';
import { computed, type Component } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateTimeInput from '../../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import IconTile from '../../../Components/IconTile.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TextBadge from '../../../Components/TextBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatTimestamp } from '../../../Utils/formatters';
import type { DataTableAction, DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';

interface ModuleRow extends Record<string, unknown> {
    moduleKey: string;
    category: string;
    technicallyAvailable: boolean;
    globallyEnabled: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    teamStateSource: string;
    globalVersion: number | null;
    teamVersion: number | null;
    supportsGlobalActivation: boolean;
    supportsTeamActivation: boolean;
    scheduledChangesCount: number;
    requiredDependencies: string;
    optionalDependencies: string;
    readOnly: boolean;
}

interface TeamRow extends Record<string, unknown> {
    publicId: string;
    name: string;
    isActive: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    source: string;
    version: number | null;
}

interface HistoryRow extends Record<string, unknown> {
    rowKey: string;
    scope: string;
    teamPublicId: string | null;
    teamName: string | null;
    previousEnabled: boolean | null;
    newEnabled: boolean;
    source: string;
    reason: string;
    effectiveAt: string;
}

interface ScheduleRow extends Record<string, unknown> {
    publicId: string;
    scope: string;
    teamPublicId: string | null;
    teamName: string | null;
    targetEnabled: boolean;
    status: string;
    reason: string;
    effectiveAt: string;
}

type MetricTone = 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';

interface DependencyItem {
    key: string;
    label: string;
    value: string;
    icon: Component;
    tone: MetricTone;
}

const props = defineProps<{
    module: ModuleRow;
    teams: TeamRow[];
    history: HistoryRow[];
    schedules: ScheduleRow[];
    exports: DataTableExportMeta;
}>();

const { locale, t } = useTranslator();
const canEditGlobal = computed(() => !props.module.readOnly && props.module.supportsGlobalActivation);
const canEditTeam = computed(() => !props.module.readOnly && props.module.supportsTeamActivation);
const canEditModule = computed(() => canEditGlobal.value || canEditTeam.value);
const globalForm = useForm({
    enabled: props.module.globallyEnabled ? 'true' : 'false',
    reason: '',
    version: props.module.globalVersion,
});
const globalScheduleForm = useForm({
    enabled: props.module.globallyEnabled ? 'false' : 'true',
    effective_at: '',
    reason: '',
});
const cancelForm = useForm({
    schedule_public_id: '',
    reason: '',
});

const enabledOptions = computed<FormSelectOption[]>(() => [
    { value: 'true', label: t('pages.admin.modules.enabled') },
    { value: 'false', label: t('pages.admin.modules.disabled') },
]);
const cancellableScheduleOptions = computed<FormSelectOption[]>(() =>
    props.schedules
        .filter((schedule) => schedule.status === 'scheduled' && schedule.scope === 'global')
        .map((schedule) => ({
            value: schedule.publicId,
            label: `${scopeLabel(schedule.scope)} - ${dateLabel(schedule.effectiveAt)}`,
        })),
);
const dependencyItems = computed<DependencyItem[]>(() => [
    {
        key: 'required',
        label: t('pages.admin.modules.required_dependencies'),
        value: props.module.requiredDependencies.trim() === '' ? t('pages.admin.modules.none') : props.module.requiredDependencies,
        icon: IconGitBranch,
        tone: 'sky',
    },
    {
        key: 'optional',
        label: t('pages.admin.modules.optional_dependencies'),
        value: props.module.optionalDependencies.trim() === '' ? t('pages.admin.modules.none') : props.module.optionalDependencies,
        icon: props.module.optionalDependencies.trim() === '' ? IconPackageOff : IconPackage,
        tone: props.module.optionalDependencies.trim() === '' ? 'zinc' : 'teal',
    },
]);
const teamRows = computed<TeamRow[]>(() =>
    props.teams.map((team) => ({
        ...team,
        source: sourceLabel(team.source),
    })),
);
const historyRows = computed<HistoryRow[]>(() =>
    props.history.map((row, index) => ({
        ...row,
        rowKey: `${row.effectiveAt}-${row.scope}-${row.teamPublicId ?? 'global'}-${index}`,
        scope: scopeLabel(row.scope),
        source: sourceLabel(row.source),
        effectiveAt: dateLabel(row.effectiveAt),
    })),
);
const scheduleRows = computed<ScheduleRow[]>(() =>
    props.schedules.map((row) => ({
        ...row,
        scope: scopeLabel(row.scope),
        status: scheduleStatusLabel(row.status),
        effectiveAt: dateLabel(row.effectiveAt),
    })),
);
const teamColumns = computed<DataTableColumn<TeamRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.modules.table.team_public_id'), hidden: true },
    { key: 'name', label: t('pages.admin.modules.table.team') },
    { key: 'isActive', label: t('pages.admin.modules.table.team_active'), format: 'boolean' },
    { key: 'teamEnabled', label: t('pages.admin.modules.table.active_team'), format: 'boolean' },
    { key: 'effectiveEnabled', label: t('pages.admin.modules.table.effective'), format: 'boolean' },
    { key: 'source', label: t('pages.admin.modules.table.team_source') },
    { key: 'version', label: t('pages.admin.modules.table.version'), format: 'number', hidden: true },
]);
const teamActions = computed<DataTableAction<TeamRow>[]>(() =>
    canEditTeam.value
        ? [
              {
                  key: 'configure',
                  label: t('pages.admin.modules.actions.configure_team'),
                  href: (team) =>
                      `/admin/modules/${encodeURIComponent(props.module.moduleKey)}/teams/create?team=${encodeURIComponent(team.publicId)}`,
              },
          ]
        : [],
);
const historyColumns = computed<DataTableColumn<HistoryRow>[]>(() => [
    { key: 'scope', label: t('pages.admin.modules.table.scope') },
    { key: 'teamName', label: t('pages.admin.modules.table.team') },
    { key: 'teamPublicId', label: t('pages.admin.modules.table.team_public_id'), hidden: true },
    { key: 'previousEnabled', label: t('pages.admin.modules.table.previous') },
    { key: 'newEnabled', label: t('pages.admin.modules.table.new'), format: 'boolean' },
    { key: 'source', label: t('pages.admin.modules.table.source') },
    { key: 'reason', label: t('pages.admin.modules.table.reason') },
    { key: 'effectiveAt', label: t('pages.admin.modules.table.effective_at') },
]);
const scheduleColumns = computed<DataTableColumn<ScheduleRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.modules.table.public_id'), hidden: true },
    { key: 'scope', label: t('pages.admin.modules.table.scope') },
    { key: 'teamName', label: t('pages.admin.modules.table.team') },
    { key: 'teamPublicId', label: t('pages.admin.modules.table.team_public_id'), hidden: true },
    { key: 'targetEnabled', label: t('pages.admin.modules.table.target_enabled'), format: 'boolean' },
    { key: 'status', label: t('pages.admin.modules.table.status') },
    { key: 'reason', label: t('pages.admin.modules.table.reason') },
    { key: 'effectiveAt', label: t('pages.admin.modules.table.effective_at') },
]);
const exportFilters = computed(() => ({ module: props.module.moduleKey }));

function submitGlobal(): void {
    globalForm
        .transform((data) => ({ ...data, enabled: data.enabled === 'true' }))
        .patch(`/admin/modules/${encodeURIComponent(props.module.moduleKey)}/global`, { preserveScroll: true });
}

function scheduleGlobal(): void {
    globalScheduleForm
        .transform((data) => ({ ...data, enabled: data.enabled === 'true' }))
        .post(`/admin/modules/${encodeURIComponent(props.module.moduleKey)}/global/schedules`, { preserveScroll: true });
}

function cancelSchedule(): void {
    if (cancelForm.schedule_public_id === '' || cancelForm.reason.trim() === '') {
        return;
    }

    router.delete(
        `/admin/modules/${encodeURIComponent(props.module.moduleKey)}/schedules/${encodeURIComponent(cancelForm.schedule_public_id)}`,
        {
            data: { reason: cancelForm.reason },
            preserveScroll: true,
        },
    );
}

function moduleCategoryLabel(category: string): string {
    const keys: Record<string, string> = {
        application: 'pages.admin.modules.categories.application',
        core: 'pages.admin.modules.categories.core',
        optional: 'pages.admin.modules.categories.optional',
    };

    return keys[category] === undefined ? category : t(keys[category]);
}

function scopeLabel(scope: string): string {
    const keys: Record<string, string> = {
        global: 'pages.admin.modules.global',
        team: 'pages.admin.modules.team',
    };

    return keys[scope] === undefined ? scope : t(keys[scope]);
}

function sourceLabel(source: string): string {
    const keys: Record<string, string> = {
        global: 'pages.admin.modules.sources.global',
        manual: 'pages.admin.modules.sources.manual',
        scheduled: 'pages.admin.modules.sources.scheduled',
        scheduler: 'pages.admin.modules.sources.scheduler',
        system: 'pages.admin.modules.sources.system',
        team: 'pages.admin.modules.sources.team',
    };

    return keys[source] === undefined ? source : t(keys[source]);
}

function scheduleStatusLabel(status: string): string {
    const keys: Record<string, string> = {
        applied: 'pages.admin.modules.schedule_status.applied',
        cancelled: 'pages.admin.modules.schedule_status.cancelled',
        failed: 'pages.admin.modules.schedule_status.failed',
        scheduled: 'pages.admin.modules.schedule_status.scheduled',
    };

    return keys[status] === undefined ? status : t(keys[status]);
}

function dateLabel(value: string): string {
    return formatTimestamp(value, locale.value);
}

function statusIcon(value: boolean): Component {
    return value ? IconCircleCheck : IconCircleX;
}

function statusTone(value: boolean): MetricTone {
    return value ? 'emerald' : 'rose';
}
</script>

<template>
    <Head :title="t('pages.admin.modules.show_title', { module: module.moduleKey })" />
    <AdminLayout :title="t('pages.admin.modules.show_title', { module: module.moduleKey })" :title-icon="IconPuzzle">
        <PageStack>
            <div class="flex justify-start">
                <ActionLink href="/admin/modules" :icon="IconArrowLeft">
                    {{ t('pages.admin.modules.back_to_modules') }}
                </ActionLink>
            </div>

            <SurfaceCard :title="module.moduleKey" :subtitle="moduleCategoryLabel(module.category)" :icon="IconPuzzle" tone="teal">
                <div class="mb-4 flex flex-wrap gap-2">
                    <TextBadge
                        :label="canEditModule ? t('pages.admin.modules.configurable') : t('pages.admin.modules.noneditable')"
                        :tone="canEditModule ? 'info' : 'neutral'"
                    />
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.available')"
                        :value="module.technicallyAvailable ? t('datatable.boolean.yes') : t('datatable.boolean.no')"
                        :icon="statusIcon(module.technicallyAvailable)"
                        :tone="statusTone(module.technicallyAvailable)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.global')"
                        :value="module.globallyEnabled ? t('pages.admin.modules.enabled') : t('pages.admin.modules.disabled')"
                        :icon="IconWorld"
                        :tone="statusTone(module.globallyEnabled)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.effective')"
                        :value="module.effectiveEnabled ? t('datatable.boolean.yes') : t('datatable.boolean.no')"
                        :icon="IconShieldCheck"
                        :tone="statusTone(module.effectiveEnabled)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.scheduled_changes')"
                        :value="module.scheduledChangesCount"
                        :icon="IconCalendarStats"
                        :tone="module.scheduledChangesCount > 0 ? 'amber' : 'zinc'"
                    />
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div
                        v-for="dependency in dependencyItems"
                        :key="dependency.key"
                        class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <IconTile :icon="dependency.icon" :tone="dependency.tone" size="sm" />
                            <span class="min-w-0">
                                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ dependency.label }}</p>
                                <p class="mt-1 break-words text-zinc-950 dark:text-zinc-50">{{ dependency.value }}</p>
                            </span>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <div v-if="canEditGlobal" class="grid gap-4">
                <SurfaceCard v-if="canEditGlobal" :title="t('pages.admin.modules.global_activation')" :icon="IconDeviceFloppy" tone="amber">
                    <div class="grid gap-5">
                        <AtlasForm :processing="globalForm.processing" @submit="submitGlobal">
                            <div class="grid gap-3">
                                <FormSelect
                                    v-model="globalForm.enabled"
                                    :label="t('pages.admin.modules.state')"
                                    :options="enabledOptions"
                                    :error="globalForm.errors.enabled"
                                />
                                <FormTextarea
                                    v-model="globalForm.reason"
                                    :label="t('pages.admin.modules.reason')"
                                    :placeholder="t('pages.admin.modules.placeholders.global_reason')"
                                    :rows="3"
                                    :error="globalForm.errors.reason"
                                />
                                <FormActions>
                                    <FormButton
                                        type="submit"
                                        :loading="globalForm.processing"
                                        :disabled="globalForm.reason.trim() === ''"
                                        :icon="IconDeviceFloppy"
                                    >
                                        {{ t('pages.admin.modules.save_global_state') }}
                                    </FormButton>
                                </FormActions>
                            </div>
                        </AtlasForm>

                        <div class="border-t border-zinc-200 pt-5 dark:border-zinc-800">
                            <AtlasForm :processing="globalScheduleForm.processing" @submit="scheduleGlobal">
                                <div class="grid gap-3">
                                    <FormSelect
                                        v-model="globalScheduleForm.enabled"
                                        :label="t('pages.admin.modules.scheduled_target')"
                                        :options="enabledOptions"
                                        :error="globalScheduleForm.errors.enabled"
                                    />
                                    <FormDateTimeInput
                                        v-model="globalScheduleForm.effective_at"
                                        :label="t('pages.admin.modules.effective_at')"
                                        :error="globalScheduleForm.errors.effective_at"
                                    />
                                    <FormTextarea
                                        v-model="globalScheduleForm.reason"
                                        :label="t('pages.admin.modules.schedule_reason')"
                                        :placeholder="t('pages.admin.modules.placeholders.schedule_reason')"
                                        :rows="3"
                                        :error="globalScheduleForm.errors.reason"
                                    />
                                    <FormActions>
                                        <FormButton
                                            type="submit"
                                            :loading="globalScheduleForm.processing"
                                            :disabled="globalScheduleForm.effective_at === '' || globalScheduleForm.reason.trim() === ''"
                                            :icon="IconCalendarTime"
                                        >
                                            {{ t('pages.admin.modules.schedule_global') }}
                                        </FormButton>
                                    </FormActions>
                                </div>
                            </AtlasForm>
                        </div>
                    </div>
                </SurfaceCard>
            </div>

            <SurfaceCard v-if="canEditGlobal" :title="t('pages.admin.modules.cancel_schedule')" :icon="IconX" tone="rose">
                <AtlasForm :processing="cancelForm.processing" @submit="cancelSchedule">
                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <FormSelect
                            v-model="cancelForm.schedule_public_id"
                            :label="t('pages.admin.modules.schedules')"
                            :options="cancellableScheduleOptions"
                            :placeholder="
                                cancellableScheduleOptions.length === 0
                                    ? t('pages.admin.modules.no_scheduled_changes')
                                    : t('pages.admin.modules.placeholders.schedule')
                            "
                            :error="cancelForm.errors.schedule_public_id"
                        />
                        <FormInput
                            v-model="cancelForm.reason"
                            :label="t('pages.admin.modules.reason')"
                            :placeholder="t('pages.admin.modules.placeholders.cancel_reason')"
                            :error="cancelForm.errors.reason"
                        />
                        <FormButton
                            type="submit"
                            tone="danger"
                            class="mt-0 lg:mt-6"
                            :disabled="cancelForm.schedule_public_id === '' || cancelForm.reason.trim() === ''"
                            :icon="IconX"
                        >
                            {{ t('pages.admin.modules.cancel') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.modules.teams')"
                :rows="teamRows"
                :columns="teamColumns"
                row-key="publicId"
                :exports="exports"
                export-key="admin.modules.detail.teams"
                :filters="exportFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.modules.empty_teams')"
                :actions="teamActions"
            />

            <div v-if="canEditModule" class="grid gap-4 xl:grid-cols-2">
                <DataTable
                    :title="t('pages.admin.modules.recent_history')"
                    :rows="historyRows"
                    :columns="historyColumns"
                    row-key="rowKey"
                    :exports="exports"
                    export-key="admin.modules.detail.history"
                    :filters="exportFilters"
                    :ui-locale="locale"
                    :empty-label="t('pages.admin.modules.no_activation_history')"
                />

                <DataTable
                    :title="t('pages.admin.modules.schedules')"
                    :rows="scheduleRows"
                    :columns="scheduleColumns"
                    row-key="publicId"
                    :exports="exports"
                    export-key="admin.modules.detail.schedules"
                    :filters="exportFilters"
                    :ui-locale="locale"
                    :empty-label="t('pages.admin.modules.no_scheduled_changes')"
                />
            </div>
        </PageStack>
    </AdminLayout>
</template>
