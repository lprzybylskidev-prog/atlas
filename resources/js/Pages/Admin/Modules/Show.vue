<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPuzzle } from '@tabler/icons-vue';
import { reactive } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTableExportMenu from '../../../Components/DataTableExportMenu.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormDateTimeInput from '../../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TextBadge from '../../../Components/TextBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableExportMeta } from '../../../Types/data-table';

interface ModuleDetails {
    moduleKey: string;
    category: string;
    technicallyAvailable: boolean;
    globallyEnabled: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    teamStateSource: string;
    globalVersion: number | null;
    supportsGlobalActivation: boolean;
    supportsTeamActivation: boolean;
    requiredDependencies: string;
    optionalDependencies: string;
    readOnly: boolean;
}

interface TeamRow {
    publicId: string;
    name: string;
    isActive: boolean;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    source: string;
    version: number | null;
}

interface HistoryRow {
    scope: string;
    teamName: string | null;
    previousEnabled: boolean | null;
    newEnabled: boolean;
    source: string;
    reason: string;
    effectiveAt: string;
}

interface ScheduleRow {
    publicId: string;
    scope: string;
    teamName: string | null;
    targetEnabled: boolean;
    status: string;
    reason: string;
    effectiveAt: string;
}

const props = defineProps<{
    module: ModuleDetails;
    teams: TeamRow[];
    history: HistoryRow[];
    schedules: ScheduleRow[];
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator();
const moduleTeamExportColumns = ['moduleKey', 'name', 'isActive', 'teamEnabled', 'effectiveEnabled', 'source', 'version'] as const;
const moduleHistoryExportColumns = [
    'moduleKey',
    'scope',
    'teamName',
    'previousEnabled',
    'newEnabled',
    'source',
    'reason',
    'effectiveAt',
] as const;
const moduleScheduleExportColumns = ['moduleKey', 'scope', 'teamName', 'targetEnabled', 'status', 'reason', 'effectiveAt'] as const;

const globalForm = useForm({
    enabled: props.module.globallyEnabled,
    reason: '',
    version: props.module.globalVersion,
});
const globalScheduleForm = useForm({
    enabled: true,
    effective_at: '',
    reason: '',
});
const teamForms = reactive<Record<string, { enabled: boolean; reason: string; version: number | null; clearReason: string }>>(
    Object.fromEntries(
        props.teams.map((team) => [
            team.publicId,
            {
                enabled: team.teamEnabled,
                reason: '',
                version: team.version,
                clearReason: '',
            },
        ]),
    ),
);
const teamScheduleForms = reactive<Record<string, { enabled: boolean; effective_at: string; reason: string }>>(
    Object.fromEntries(
        props.teams.map((team) => [
            team.publicId,
            {
                enabled: true,
                effective_at: '',
                reason: '',
            },
        ]),
    ),
);

function submitGlobal(): void {
    globalForm.patch(`/admin/modules/${props.module.moduleKey}/global`, { preserveScroll: true });
}

function scheduleGlobal(): void {
    globalScheduleForm.post(`/admin/modules/${props.module.moduleKey}/global/schedules`, { preserveScroll: true });
}

function teamForm(team: TeamRow): { enabled: boolean; reason: string; version: number | null; clearReason: string } {
    teamForms[team.publicId] ??= {
        enabled: team.teamEnabled,
        reason: '',
        version: team.version,
        clearReason: '',
    };

    return teamForms[team.publicId];
}

function teamScheduleForm(team: TeamRow): { enabled: boolean; effective_at: string; reason: string } {
    teamScheduleForms[team.publicId] ??= {
        enabled: true,
        effective_at: '',
        reason: '',
    };

    return teamScheduleForms[team.publicId];
}

function submitTeam(team: TeamRow): void {
    const values = teamForm(team);

    router.patch(
        `/admin/modules/${props.module.moduleKey}/teams/${team.publicId}`,
        {
            enabled: values.enabled,
            reason: values.reason,
            version: values.version,
        },
        { preserveScroll: true },
    );
}

function clearTeam(team: TeamRow): void {
    const values = teamForm(team);

    router.delete(`/admin/modules/${props.module.moduleKey}/teams/${team.publicId}`, {
        data: { reason: values.clearReason },
        preserveScroll: true,
    });
}

function scheduleTeam(team: TeamRow): void {
    const values = teamScheduleForm(team);

    router.post(
        `/admin/modules/${props.module.moduleKey}/teams/${team.publicId}/schedules`,
        {
            enabled: values.enabled,
            effective_at: values.effective_at,
            reason: values.reason,
        },
        { preserveScroll: true },
    );
}

function cancelSchedule(publicId: string, reason: string): void {
    router.delete(`/admin/modules/${props.module.moduleKey}/schedules/${publicId}`, {
        data: { reason },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.modules.show_title', { module: module.moduleKey })" />
    <AdminLayout :title="t('pages.admin.modules.show_title', { module: module.moduleKey })" :title-icon="IconPuzzle">
        <PageStack>
            <ActionLink href="/admin/modules" :icon="IconArrowLeft">{{ t('pages.admin.modules.back_to_modules') }}</ActionLink>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-5">
                    <SurfaceCard :title="t('pages.admin.modules.global_activation')" :icon="IconPuzzle">
                        <AtlasForm :processing="globalForm.processing" @submit="submitGlobal">
                            <div class="grid gap-4 md:grid-cols-[auto_minmax(0,1fr)_auto]">
                                <FormCheckbox v-model="globalForm.enabled" :disabled="module.readOnly || !module.supportsGlobalActivation">
                                    {{ t('pages.admin.modules.globally_enabled') }}
                                </FormCheckbox>
                                <FormInput
                                    v-model="globalForm.reason"
                                    :label="t('pages.admin.modules.reason')"
                                    :error="globalForm.errors.reason"
                                />
                                <FormButton
                                    type="submit"
                                    class="mt-0 md:mt-6"
                                    :disabled="module.readOnly || !module.supportsGlobalActivation || !globalForm.reason.trim()"
                                    :loading="globalForm.processing"
                                >
                                    {{ t('pages.admin.modules.save_global_state') }}
                                </FormButton>
                            </div>
                        </AtlasForm>
                    </SurfaceCard>

                    <SurfaceCard :title="t('pages.admin.modules.global_schedule')" :icon="IconPuzzle">
                        <AtlasForm :processing="globalScheduleForm.processing" @submit="scheduleGlobal">
                            <div class="grid gap-4 xl:grid-cols-[auto_minmax(0,14rem)_minmax(0,1fr)_auto]">
                                <FormCheckbox
                                    v-model="globalScheduleForm.enabled"
                                    :disabled="module.readOnly || !module.supportsGlobalActivation"
                                    class="mt-0 xl:mt-6"
                                >
                                    {{ t('pages.admin.modules.target_enabled') }}
                                </FormCheckbox>
                                <FormDateTimeInput
                                    v-model="globalScheduleForm.effective_at"
                                    :label="t('pages.admin.modules.effective_at')"
                                    :error="globalScheduleForm.errors.effective_at"
                                />
                                <FormInput
                                    v-model="globalScheduleForm.reason"
                                    :label="t('pages.admin.modules.reason')"
                                    :error="globalScheduleForm.errors.reason"
                                />
                                <FormButton
                                    type="submit"
                                    class="mt-0 xl:mt-6"
                                    :disabled="
                                        module.readOnly ||
                                        !module.supportsGlobalActivation ||
                                        !globalScheduleForm.reason.trim() ||
                                        !globalScheduleForm.effective_at
                                    "
                                    :loading="globalScheduleForm.processing"
                                >
                                    {{ t('pages.admin.modules.schedule_global') }}
                                </FormButton>
                            </div>
                        </AtlasForm>
                    </SurfaceCard>

                    <SurfaceCard :title="t('pages.admin.modules.teams')" :icon="IconPuzzle">
                        <template #actions>
                            <DataTableExportMenu
                                table-key="admin.modules.detail.teams"
                                :exports="exports"
                                :columns="[...moduleTeamExportColumns]"
                                :column-order="[...moduleTeamExportColumns]"
                                :filters="{ module: module.moduleKey }"
                                sort="name"
                            />
                        </template>
                        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                            <div v-for="team in teams" :key="team.publicId" class="space-y-4 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ team.name }}</p>
                                        <p class="mt-1 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ team.publicId }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <StatusBadge :value="team.isActive" />
                                        <StatusBadge :value="team.effectiveEnabled" />
                                        <TextBadge :label="team.source" />
                                    </div>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[auto_minmax(0,1fr)_auto]">
                                    <FormCheckbox
                                        v-model="teamForm(team).enabled"
                                        :disabled="module.readOnly || !module.supportsTeamActivation"
                                    >
                                        {{ t('pages.admin.modules.enabled_override') }}
                                    </FormCheckbox>
                                    <FormInput v-model="teamForm(team).reason" :label="t('pages.admin.modules.override_reason')" />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        :disabled="module.readOnly || !module.supportsTeamActivation || !teamForm(team).reason.trim()"
                                        @click="submitTeam(team)"
                                    >
                                        {{ t('pages.admin.modules.save_override') }}
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                                    <FormInput
                                        v-model="teamForm(team).clearReason"
                                        :label="t('pages.admin.modules.clear_override_reason')"
                                    />
                                    <FormButton
                                        type="button"
                                        tone="danger"
                                        class="mt-0 xl:mt-6"
                                        :disabled="team.source !== 'team' || !teamForm(team).clearReason.trim()"
                                        @click="clearTeam(team)"
                                    >
                                        {{ t('pages.admin.modules.inherit_global') }}
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[auto_minmax(0,14rem)_minmax(0,1fr)_auto]">
                                    <FormCheckbox
                                        v-model="teamScheduleForm(team).enabled"
                                        :disabled="module.readOnly || !module.supportsTeamActivation"
                                        class="mt-0 xl:mt-6"
                                    >
                                        {{ t('pages.admin.modules.scheduled_target') }}
                                    </FormCheckbox>
                                    <FormDateTimeInput
                                        v-model="teamScheduleForm(team).effective_at"
                                        :label="t('pages.admin.modules.effective_at')"
                                    />
                                    <FormInput v-model="teamScheduleForm(team).reason" :label="t('pages.admin.modules.schedule_reason')" />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        :disabled="
                                            module.readOnly ||
                                            !module.supportsTeamActivation ||
                                            !teamScheduleForm(team).reason.trim() ||
                                            !teamScheduleForm(team).effective_at
                                        "
                                        @click="scheduleTeam(team)"
                                    >
                                        {{ t('pages.admin.modules.schedule') }}
                                    </FormButton>
                                </div>
                            </div>
                        </div>
                    </SurfaceCard>
                </div>

                <aside class="space-y-5">
                    <NoticeBanner :title="t('pages.admin.modules.bounded_title')">
                        {{ t('pages.admin.modules.bounded_history') }}
                    </NoticeBanner>

                    <SurfaceCard :title="t('pages.admin.modules.state')" :icon="IconPuzzle">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.modules.category') }}</dt>
                                <dd>{{ module.category }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.modules.available') }}</dt>
                                <dd><StatusBadge :value="module.technicallyAvailable" /></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.modules.global') }}</dt>
                                <dd><StatusBadge :value="module.globallyEnabled" /></dd>
                            </div>
                        </dl>
                    </SurfaceCard>

                    <SurfaceCard :title="t('pages.admin.modules.recent_history')" :icon="IconPuzzle">
                        <template #actions>
                            <DataTableExportMenu
                                table-key="admin.modules.detail.history"
                                :exports="exports"
                                :columns="[...moduleHistoryExportColumns]"
                                :column-order="[...moduleHistoryExportColumns]"
                                :filters="{ module: module.moduleKey }"
                                sort="effectiveAt"
                                direction="desc"
                            />
                        </template>
                        <div class="space-y-3 text-sm">
                            <p v-if="history.length === 0" class="text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.modules.no_activation_history') }}
                            </p>
                            <div v-for="row in history" :key="`${row.scope}-${row.effectiveAt}-${row.reason}`">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.scope }} · {{ row.source }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.effectiveAt }}</p>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ row.reason }}</p>
                            </div>
                        </div>
                    </SurfaceCard>

                    <SurfaceCard :title="t('pages.admin.modules.schedules')" :icon="IconPuzzle">
                        <template #actions>
                            <DataTableExportMenu
                                table-key="admin.modules.detail.schedules"
                                :exports="exports"
                                :columns="[...moduleScheduleExportColumns]"
                                :column-order="[...moduleScheduleExportColumns]"
                                :filters="{ module: module.moduleKey }"
                                sort="effectiveAt"
                                direction="desc"
                            />
                        </template>
                        <div class="space-y-3 text-sm">
                            <p v-if="schedules.length === 0" class="text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.modules.no_scheduled_changes') }}
                            </p>
                            <div v-for="row in schedules" :key="row.publicId" class="space-y-2">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.scope }} · {{ row.status }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.effectiveAt }}</p>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ row.reason }}</p>
                                <FormButton
                                    v-if="row.status === 'scheduled'"
                                    type="button"
                                    tone="danger"
                                    @click="
                                        cancelSchedule(row.publicId, t('pages.admin.modules.cancel_reason', { module: module.moduleKey }))
                                    "
                                >
                                    {{ t('pages.admin.modules.cancel') }}
                                </FormButton>
                            </div>
                        </div>
                    </SurfaceCard>
                </aside>
            </div>
        </PageStack>
    </AdminLayout>
</template>
