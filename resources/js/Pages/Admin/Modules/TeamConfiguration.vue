<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconCalendarStats,
    IconCalendarTime,
    IconDeviceFloppy,
    IconEraser,
    IconGitBranch,
    IconPuzzle,
    IconShieldCheck,
    IconUsersGroup,
    IconX,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateTimeInput from '../../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import { moduleScheduleStatusLabel, moduleScopeLabel, moduleSourceLabel } from '../../../Composables/useModuleActivationUi';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatTimestamp } from '../../../Utils/formatters';
import type { DataTableColumn } from '../../../Types/data-table';

interface ModuleRow extends Record<string, unknown> {
    moduleKey: string;
    category: string;
    technicallyAvailable: boolean;
    globallyEnabled: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    teamStateSource: string;
    teamVersion: number | null;
    scheduledChangesCount: number;
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

const props = defineProps<{
    module: ModuleRow;
    selectedTeamPublicId: string | null;
    teams: TeamRow[];
    history: HistoryRow[];
    schedules: ScheduleRow[];
}>();

const { locale, t } = useTranslator();
const selectedTeam = computed(() => props.teams.find((team) => team.publicId === props.selectedTeamPublicId));
const enabledOptions = computed<FormSelectOption[]>(() => [
    { value: 'true', label: t('pages.admin.modules.enabled') },
    { value: 'false', label: t('pages.admin.modules.disabled') },
]);
const teamForm = useForm({
    enabled: selectedTeam.value?.teamEnabled === true ? 'true' : 'false',
    reason: '',
    version: selectedTeam.value?.version ?? null,
});
const teamScheduleForm = useForm({
    enabled: selectedTeam.value?.effectiveEnabled === true ? 'false' : 'true',
    effective_at: '',
    reason: '',
});
const clearTeamForm = useForm({
    reason: '',
});
const cancelForm = useForm({
    schedule_public_id: '',
    reason: '',
});
const selectedTeamSchedules = computed<ScheduleRow[]>(() =>
    props.schedules.filter((schedule) => schedule.scope === 'team' && schedule.teamPublicId === props.selectedTeamPublicId),
);
const cancellableScheduleOptions = computed<FormSelectOption[]>(() =>
    selectedTeamSchedules.value
        .filter((schedule) => schedule.status === 'scheduled')
        .map((schedule) => ({
            value: schedule.publicId,
            label: `${schedule.teamName ?? t('pages.admin.modules.team')} - ${dateLabel(schedule.effectiveAt)}`,
        })),
);
const selectedHistoryRows = computed<HistoryRow[]>(() =>
    props.history
        .filter((row) => row.scope === 'team' && row.teamPublicId === props.selectedTeamPublicId)
        .map((row, index) => ({
            ...row,
            rowKey: `${row.effectiveAt}-${row.scope}-${row.teamPublicId ?? 'team'}-${index}`,
            scope: scopeLabel(row.scope),
            source: sourceLabel(row.source),
            effectiveAt: dateLabel(row.effectiveAt),
        })),
);
const selectedScheduleRows = computed<ScheduleRow[]>(() =>
    selectedTeamSchedules.value.map((row) => ({
        ...row,
        scope: scopeLabel(row.scope),
        status: scheduleStatusLabel(row.status),
        effectiveAt: dateLabel(row.effectiveAt),
    })),
);
const historyColumns = computed<DataTableColumn<HistoryRow>[]>(() => [
    { key: 'scope', label: t('pages.admin.modules.table.scope') },
    { key: 'previousEnabled', label: t('pages.admin.modules.table.previous') },
    { key: 'newEnabled', label: t('pages.admin.modules.table.new'), format: 'boolean' },
    { key: 'source', label: t('pages.admin.modules.table.source') },
    { key: 'reason', label: t('pages.admin.modules.table.reason') },
    { key: 'effectiveAt', label: t('pages.admin.modules.table.effective_at') },
]);
const scheduleColumns = computed<DataTableColumn<ScheduleRow>[]>(() => [
    { key: 'publicId', label: t('pages.admin.modules.table.public_id'), hidden: true },
    { key: 'scope', label: t('pages.admin.modules.table.scope') },
    { key: 'targetEnabled', label: t('pages.admin.modules.table.target_enabled'), format: 'boolean' },
    { key: 'status', label: t('pages.admin.modules.table.status') },
    { key: 'reason', label: t('pages.admin.modules.table.reason') },
    { key: 'effectiveAt', label: t('pages.admin.modules.table.effective_at') },
]);
const hasTeamOverride = computed(() => selectedTeam.value?.source === 'team');

function submitTeam(): void {
    const team = selectedTeam.value;

    if (team === undefined) {
        return;
    }

    teamForm.version = team.version;
    teamForm
        .transform((data) => ({ ...data, enabled: data.enabled === 'true' }))
        .patch(`/admin/modules/${encodeURIComponent(props.module.moduleKey)}/teams/${encodeURIComponent(team.publicId)}`, {
            preserveScroll: true,
        });
}

function scheduleTeam(): void {
    const team = selectedTeam.value;

    if (team === undefined) {
        return;
    }

    teamScheduleForm
        .transform((data) => ({ ...data, enabled: data.enabled === 'true' }))
        .post(`/admin/modules/${encodeURIComponent(props.module.moduleKey)}/teams/${encodeURIComponent(team.publicId)}/schedules`, {
            preserveScroll: true,
        });
}

function clearTeam(): void {
    const team = selectedTeam.value;

    if (team === undefined || clearTeamForm.reason.trim() === '') {
        return;
    }

    router.delete(`/admin/modules/${encodeURIComponent(props.module.moduleKey)}/teams/${encodeURIComponent(team.publicId)}`, {
        data: { reason: clearTeamForm.reason },
        preserveScroll: true,
    });
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

function scopeLabel(scope: string): string {
    return moduleScopeLabel(scope, t);
}

function sourceLabel(source: string): string {
    return moduleSourceLabel(source, t);
}

function scheduleStatusLabel(status: string): string {
    return moduleScheduleStatusLabel(status, t);
}

function dateLabel(value: string): string {
    return formatTimestamp(value, locale.value);
}

function statusTone(value: boolean): MetricTone {
    return value ? 'emerald' : 'rose';
}
</script>

<template>
    <Head :title="t('pages.admin.modules.team_configuration_title', { module: module.moduleKey })" />
    <AppLayout
        mode="admin"
        :title="t('pages.admin.modules.team_configuration_title', { module: module.moduleKey })"
        :title-icon="IconUsersGroup"
    >
        <PageStack>
            <div class="flex flex-wrap gap-2">
                <ActionLink :href="`/admin/modules/${encodeURIComponent(module.moduleKey)}`" :icon="IconArrowLeft">
                    {{ t('pages.admin.modules.back_to_module') }}
                </ActionLink>
                <ActionLink href="/admin/modules" :icon="IconPuzzle">
                    {{ t('pages.admin.modules.back_to_modules') }}
                </ActionLink>
            </div>

            <SurfaceCard
                :title="module.moduleKey"
                :subtitle="selectedTeam?.name ?? t('pages.admin.modules.team_configuration')"
                :icon="IconUsersGroup"
                tone="teal"
            >
                <div v-if="selectedTeam" class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.global')"
                        :value="module.globallyEnabled ? t('pages.admin.modules.enabled') : t('pages.admin.modules.disabled')"
                        :icon="IconPuzzle"
                        :tone="statusTone(module.globallyEnabled)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.active_team')"
                        :value="selectedTeam.teamEnabled ? t('pages.admin.modules.enabled') : t('pages.admin.modules.disabled')"
                        :icon="IconUsersGroup"
                        :tone="statusTone(selectedTeam.teamEnabled)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.effective')"
                        :value="selectedTeam.effectiveEnabled ? t('datatable.boolean.yes') : t('datatable.boolean.no')"
                        :icon="IconShieldCheck"
                        :tone="statusTone(selectedTeam.effectiveEnabled)"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.team_source')"
                        :value="sourceLabel(selectedTeam.source)"
                        :icon="IconGitBranch"
                        tone="sky"
                    />
                    <OperationalMetricTile
                        :label="t('pages.admin.modules.scheduled_changes')"
                        :value="selectedTeamSchedules.length"
                        :icon="IconCalendarStats"
                        :tone="selectedTeamSchedules.length > 0 ? 'amber' : 'zinc'"
                    />
                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <p class="mb-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.modules.table.team_active') }}
                        </p>
                        <StatusBadge
                            :value="selectedTeam.isActive"
                            :true-label="t('datatable.status.active')"
                            :false-label="t('datatable.status.inactive')"
                        />
                    </div>
                </div>
            </SurfaceCard>

            <div v-if="selectedTeam" class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.admin.modules.team_activation')" :icon="IconDeviceFloppy" tone="teal">
                    <AtlasForm :processing="teamForm.processing" @submit="submitTeam">
                        <div class="grid gap-3">
                            <FormSelect
                                v-model="teamForm.enabled"
                                :label="t('pages.admin.modules.enabled_override')"
                                :options="enabledOptions"
                                :error="teamForm.errors.enabled"
                            />
                            <FormTextarea
                                v-model="teamForm.reason"
                                :label="t('pages.admin.modules.override_reason')"
                                :placeholder="t('pages.admin.modules.placeholders.team_reason')"
                                :rows="3"
                                :error="teamForm.errors.reason"
                            />
                            <FormActions>
                                <FormButton
                                    type="submit"
                                    :loading="teamForm.processing"
                                    :disabled="teamForm.reason.trim() === ''"
                                    :icon="IconDeviceFloppy"
                                >
                                    {{ t('pages.admin.modules.save_override') }}
                                </FormButton>
                            </FormActions>
                        </div>
                    </AtlasForm>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.admin.modules.team_schedule')" :icon="IconCalendarTime" tone="amber">
                    <AtlasForm :processing="teamScheduleForm.processing" @submit="scheduleTeam">
                        <div class="grid gap-3">
                            <FormSelect
                                v-model="teamScheduleForm.enabled"
                                :label="t('pages.admin.modules.scheduled_target')"
                                :options="enabledOptions"
                                :error="teamScheduleForm.errors.enabled"
                            />
                            <FormDateTimeInput
                                v-model="teamScheduleForm.effective_at"
                                :label="t('pages.admin.modules.effective_at')"
                                :error="teamScheduleForm.errors.effective_at"
                            />
                            <FormTextarea
                                v-model="teamScheduleForm.reason"
                                :label="t('pages.admin.modules.schedule_reason')"
                                :placeholder="t('pages.admin.modules.placeholders.schedule_reason')"
                                :rows="3"
                                :error="teamScheduleForm.errors.reason"
                            />
                            <FormActions>
                                <FormButton
                                    type="submit"
                                    :loading="teamScheduleForm.processing"
                                    :disabled="teamScheduleForm.effective_at === '' || teamScheduleForm.reason.trim() === ''"
                                    :icon="IconCalendarTime"
                                >
                                    {{ t('pages.admin.modules.schedule') }}
                                </FormButton>
                            </FormActions>
                        </div>
                    </AtlasForm>
                </SurfaceCard>
            </div>

            <SurfaceCard v-if="selectedTeam" :title="t('pages.admin.modules.team_cleanup')" :icon="IconEraser" tone="rose">
                <div class="grid gap-5">
                    <AtlasForm :processing="clearTeamForm.processing" @submit="clearTeam">
                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                            <FormInput
                                v-model="clearTeamForm.reason"
                                :label="t('pages.admin.modules.clear_override_reason')"
                                :placeholder="t('pages.admin.modules.placeholders.clear_reason')"
                                :error="clearTeamForm.errors.reason"
                            />
                            <FormButton
                                type="submit"
                                tone="danger"
                                class="mt-0 lg:mt-6"
                                :disabled="!hasTeamOverride || clearTeamForm.reason.trim() === ''"
                                :icon="IconEraser"
                            >
                                {{ t('pages.admin.modules.actions.clear_override') }}
                            </FormButton>
                        </div>
                    </AtlasForm>

                    <div class="border-t border-zinc-200 pt-5 dark:border-zinc-800">
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
                    </div>
                </div>
            </SurfaceCard>

            <div v-if="selectedTeam" class="grid gap-4 xl:grid-cols-2">
                <DataTable
                    :title="t('pages.admin.modules.recent_history')"
                    :rows="selectedHistoryRows"
                    :columns="historyColumns"
                    row-key="rowKey"
                    :ui-locale="locale"
                    :empty-label="t('pages.admin.modules.no_activation_history')"
                />

                <DataTable
                    :title="t('pages.admin.modules.schedules')"
                    :rows="selectedScheduleRows"
                    :columns="scheduleColumns"
                    row-key="publicId"
                    :ui-locale="locale"
                    :empty-label="t('pages.admin.modules.no_scheduled_changes')"
                />
            </div>
        </PageStack>
    </AppLayout>
</template>
