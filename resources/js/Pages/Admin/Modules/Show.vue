<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPuzzle } from '@tabler/icons-vue';
import { reactive } from 'vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

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
}>();

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
    <Head :title="`Module ${module.moduleKey}`" />
    <AdminLayout :title="`Module ${module.moduleKey}`" :title-icon="IconPuzzle">
        <section class="space-y-5">
            <AdminActionLink href="/admin/modules" :icon="IconArrowLeft"> Back to modules </AdminActionLink>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-5">
                    <AtlasForm
                        class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                        :processing="globalForm.processing"
                        @submit="submitGlobal"
                    >
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Global activation</h2>
                        <div class="mt-4 grid gap-4 md:grid-cols-[auto_minmax(0,1fr)_auto]">
                            <FormCheckbox v-model="globalForm.enabled" :disabled="module.readOnly || !module.supportsGlobalActivation">
                                Globally enabled
                            </FormCheckbox>
                            <FormInput v-model="globalForm.reason" label="Reason" :error="globalForm.errors.reason" />
                            <FormButton
                                type="submit"
                                class="mt-0 md:mt-6"
                                :disabled="module.readOnly || !module.supportsGlobalActivation || !globalForm.reason.trim()"
                                :loading="globalForm.processing"
                            >
                                Save global state
                            </FormButton>
                        </div>
                    </AtlasForm>

                    <AtlasForm
                        class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                        :processing="globalScheduleForm.processing"
                        @submit="scheduleGlobal"
                    >
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Global schedule</h2>
                        <div class="mt-4 grid gap-4 xl:grid-cols-[auto_minmax(0,14rem)_minmax(0,1fr)_auto]">
                            <FormCheckbox
                                v-model="globalScheduleForm.enabled"
                                :disabled="module.readOnly || !module.supportsGlobalActivation"
                                class="mt-0 xl:mt-6"
                            >
                                Target enabled
                            </FormCheckbox>
                            <FormInput
                                v-model="globalScheduleForm.effective_at"
                                type="datetime-local"
                                label="Effective at"
                                :error="globalScheduleForm.errors.effective_at"
                            />
                            <FormInput v-model="globalScheduleForm.reason" label="Reason" :error="globalScheduleForm.errors.reason" />
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
                                Schedule global
                            </FormButton>
                        </div>
                    </AtlasForm>

                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Teams</h2>
                        <div
                            class="mt-4 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                        >
                            <div v-for="team in teams" :key="team.publicId" class="space-y-4 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ team.name }}</p>
                                        <p class="mt-1 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ team.publicId }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <StatusBadge :value="team.isActive" />
                                        <StatusBadge :value="team.effectiveEnabled" />
                                        <span
                                            class="rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300"
                                        >
                                            {{ team.source }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[auto_minmax(0,1fr)_auto]">
                                    <FormCheckbox
                                        v-model="teamForm(team).enabled"
                                        :disabled="module.readOnly || !module.supportsTeamActivation"
                                    >
                                        Enabled override
                                    </FormCheckbox>
                                    <FormInput v-model="teamForm(team).reason" label="Override reason" />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        :disabled="module.readOnly || !module.supportsTeamActivation || !teamForm(team).reason.trim()"
                                        @click="submitTeam(team)"
                                    >
                                        Save override
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                                    <FormInput v-model="teamForm(team).clearReason" label="Clear override reason" />
                                    <FormButton
                                        type="button"
                                        tone="danger"
                                        class="mt-0 xl:mt-6"
                                        :disabled="team.source !== 'team' || !teamForm(team).clearReason.trim()"
                                        @click="clearTeam(team)"
                                    >
                                        Inherit global
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[auto_minmax(0,14rem)_minmax(0,1fr)_auto]">
                                    <FormCheckbox
                                        v-model="teamScheduleForm(team).enabled"
                                        :disabled="module.readOnly || !module.supportsTeamActivation"
                                        class="mt-0 xl:mt-6"
                                    >
                                        Scheduled target
                                    </FormCheckbox>
                                    <FormInput v-model="teamScheduleForm(team).effective_at" type="datetime-local" label="Effective at" />
                                    <FormInput v-model="teamScheduleForm(team).reason" label="Schedule reason" />
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
                                        Schedule
                                    </FormButton>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">State</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">Category</dt>
                                <dd>{{ module.category }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">Available</dt>
                                <dd><StatusBadge :value="module.technicallyAvailable" /></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-zinc-500 dark:text-zinc-400">Global</dt>
                                <dd><StatusBadge :value="module.globallyEnabled" /></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Recent history</h2>
                        <div class="mt-3 space-y-3 text-sm">
                            <p v-if="history.length === 0" class="text-zinc-500 dark:text-zinc-400">No activation history.</p>
                            <div v-for="row in history" :key="`${row.scope}-${row.effectiveAt}-${row.reason}`">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.scope }} · {{ row.source }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.effectiveAt }}</p>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ row.reason }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Schedules</h2>
                        <div class="mt-3 space-y-3 text-sm">
                            <p v-if="schedules.length === 0" class="text-zinc-500 dark:text-zinc-400">No scheduled changes.</p>
                            <div v-for="row in schedules" :key="row.publicId" class="space-y-2">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ row.scope }} · {{ row.status }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.effectiveAt }}</p>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ row.reason }}</p>
                                <FormButton
                                    v-if="row.status === 'scheduled'"
                                    type="button"
                                    tone="danger"
                                    @click="cancelSchedule(row.publicId, `Cancelled from module ${module.moduleKey} administration.`)"
                                >
                                    Cancel
                                </FormButton>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
