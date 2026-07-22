<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconFlag3, IconHistory, IconShieldLock, IconUsersGroup } from '@tabler/icons-vue';
import type { Component } from 'vue';
import { computed } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import MetricGrid from '../../../Components/MetricGrid.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { DataTableColumn } from '../../../Types/data-table';

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
}

interface TeamOption {
    publicId: string;
    name: string;
}

interface HistoryRow extends Record<string, unknown> {
    publicId: string;
    flagKey: string;
    scope: string;
    teamPublicId: string | null;
    teamName: string | null;
    action: string;
    reason: string;
    before: { enabled?: boolean } | null;
    after: { enabled?: boolean } | null;
    actorPublicId: string;
    createdAt: string;
}

const props = defineProps<{
    flags: FeatureFlagRow[];
    teams: TeamOption[];
    selectedTeamPublicId: string | null;
    history: HistoryRow[];
}>();

const form = useForm({
    flag_key: props.flags[0]?.key ?? '',
    scope: 'team',
    team_public_id: props.selectedTeamPublicId ?? props.teams[0]?.publicId ?? '',
    enabled: false,
    reason: '',
});

const flagOptions = computed(() => props.flags.map((flag) => ({ value: flag.key, label: `${flag.name} (${flag.key})` })));
const teamOptions = computed(() => props.teams.map((team) => ({ value: team.publicId, label: team.name })));
const selectedFlag = computed(() => props.flags.find((flag) => flag.key === form.flag_key) ?? props.flags[0] ?? null);
const selectedTeam = computed(() => props.teams.find((team) => team.publicId === form.team_public_id) ?? null);
const canClearTeam = computed(() => selectedFlag.value?.teamEnabled !== null && form.scope === 'team');

const summaryItems = computed<{ label: string; value: string; icon: Component; tone: string }[]>(() => [
    { label: 'Registered flags', value: String(props.flags.length), icon: IconFlag3, tone: 'teal' },
    {
        label: 'Enabled effectively',
        value: String(props.flags.filter((flag) => flag.effectiveEnabled).length),
        icon: IconShieldLock,
        tone: 'emerald',
    },
    {
        label: 'Team overrides',
        value: String(props.flags.filter((flag) => flag.teamEnabled !== null).length),
        icon: IconUsersGroup,
        tone: 'sky',
    },
    { label: 'History rows', value: String(props.history.length), icon: IconHistory, tone: 'amber' },
]);

const flagColumns: DataTableColumn<FeatureFlagRow>[] = [
    { key: 'name', label: 'Flag' },
    { key: 'key', label: 'Key' },
    { key: 'ownerModule', label: 'Owner module' },
    { key: 'type', label: 'Type' },
    { key: 'defaultEnabled', label: 'Default', format: 'boolean' },
    { key: 'globalEnabled', label: 'Global', format: 'boolean' },
    { key: 'teamEnabled', label: 'Team', format: 'boolean' },
    { key: 'effectiveEnabled', label: 'Effective', format: 'boolean' },
    { key: 'source', label: 'Source', format: 'status' },
    { key: 'lifecycle', label: 'Lifecycle', hidden: true },
    { key: 'description', label: 'Description', hidden: true },
];

const historyColumns: DataTableColumn<HistoryRow>[] = [
    { key: 'createdAt', label: 'Changed', format: 'datetime' },
    { key: 'flagKey', label: 'Flag' },
    { key: 'scope', label: 'Scope', format: 'status' },
    { key: 'teamName', label: 'Team' },
    { key: 'action', label: 'Action' },
    { key: 'reason', label: 'Reason' },
    { key: 'actorPublicId', label: 'Actor', hidden: true },
];

function refreshForTeam(): void {
    router.get('/admin/feature-flags', { team: form.team_public_id }, { preserveScroll: true, preserveState: true });
}

function submit(): void {
    const flag = form.flag_key;

    if (form.scope === 'global') {
        form.patch(`/admin/feature-flags/${flag}/global`, { preserveScroll: true });
        return;
    }

    form.patch(`/admin/feature-flags/${flag}/teams`, { preserveScroll: true });
}

function clearTeam(): void {
    form.delete(`/admin/feature-flags/${form.flag_key}/teams`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Feature flags" />
    <AdminLayout title="Feature flags" :title-icon="IconFlag3">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard
                title="Change flag value"
                :icon="IconFlag3"
                subtitle="Global values apply first; team overrides apply only to the selected team."
            >
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <FormSelect v-model="form.flag_key" label="Flag" :options="flagOptions" />
                        <FormSelect
                            v-model="form.scope"
                            label="Scope"
                            :options="[
                                { value: 'team', label: 'Selected team' },
                                { value: 'global', label: 'Global' },
                            ]"
                        />
                        <FormSelect
                            v-model="form.team_public_id"
                            label="Team"
                            :options="teamOptions"
                            :button-class="form.scope === 'global' ? 'opacity-60' : ''"
                            @update:model-value="refreshForTeam"
                        />
                        <div class="flex items-end">
                            <FormCheckbox v-model="form.enabled" label="Enabled" />
                        </div>
                    </div>
                    <FormInput
                        v-model="form.reason"
                        label="Reason"
                        :error="form.errors.reason"
                        placeholder="Explain the rollout or rollback decision"
                    />
                    <div class="flex flex-wrap items-center gap-3">
                        <FormButton type="submit" :icon="IconFlag3" :loading="form.processing">Save value</FormButton>
                        <FormButton
                            v-if="canClearTeam"
                            type="button"
                            tone="neutral"
                            :icon="IconUsersGroup"
                            :loading="form.processing"
                            @click="clearTeam"
                        >
                            Clear team override
                        </FormButton>
                    </div>
                    <p v-if="selectedFlag" class="text-sm text-zinc-600 dark:text-zinc-300">
                        Editing {{ selectedFlag.name }} for
                        {{ form.scope === 'global' ? 'all teams' : (selectedTeam?.name ?? 'the selected team') }}.
                    </p>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                title="Registered feature flags"
                :rows="flags"
                :columns="flagColumns"
                row-key="key"
                state-key="admin.feature-flags.flags"
                empty-label="No feature flags are registered."
            />

            <DataTable
                title="Recent feature flag history"
                :rows="history"
                :columns="historyColumns"
                row-key="publicId"
                state-key="admin.feature-flags.history"
                empty-label="No feature flag changes recorded."
            />
        </PageStack>
    </AdminLayout>
</template>
