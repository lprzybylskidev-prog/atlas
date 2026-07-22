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
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableExportMeta } from '../../../Types/data-table';

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
    exports: DataTableExportMeta;
}>();

const { t } = useTranslator();
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
    { label: t('pages.admin.feature_flags.metric.registered'), value: String(props.flags.length), icon: IconFlag3, tone: 'teal' },
    {
        label: t('pages.admin.feature_flags.metric.enabled_effectively'),
        value: String(props.flags.filter((flag) => flag.effectiveEnabled).length),
        icon: IconShieldLock,
        tone: 'emerald',
    },
    {
        label: t('pages.admin.feature_flags.metric.team_overrides'),
        value: String(props.flags.filter((flag) => flag.teamEnabled !== null).length),
        icon: IconUsersGroup,
        tone: 'sky',
    },
    { label: t('pages.admin.feature_flags.metric.history_rows'), value: String(props.history.length), icon: IconHistory, tone: 'amber' },
]);

const flagColumns: DataTableColumn<FeatureFlagRow>[] = [
    { key: 'name', label: t('pages.admin.feature_flags.flag') },
    { key: 'key', label: t('pages.admin.feature_flags.key') },
    { key: 'ownerModule', label: t('pages.admin.feature_flags.owner_module') },
    { key: 'type', label: t('pages.admin.feature_flags.type') },
    { key: 'defaultEnabled', label: t('pages.admin.feature_flags.default'), format: 'boolean' },
    { key: 'globalEnabled', label: t('pages.admin.feature_flags.global'), format: 'boolean' },
    { key: 'teamEnabled', label: t('pages.admin.feature_flags.team'), format: 'boolean' },
    { key: 'effectiveEnabled', label: t('pages.admin.feature_flags.effective'), format: 'boolean' },
    { key: 'source', label: t('pages.admin.feature_flags.source'), format: 'status' },
    { key: 'lifecycle', label: t('pages.admin.feature_flags.lifecycle'), hidden: true },
    { key: 'description', label: t('pages.admin.feature_flags.description'), hidden: true },
];

const historyColumns: DataTableColumn<HistoryRow>[] = [
    { key: 'createdAt', label: t('pages.admin.feature_flags.changed'), format: 'datetime' },
    { key: 'flagKey', label: t('pages.admin.feature_flags.flag') },
    { key: 'scope', label: t('pages.admin.feature_flags.scope'), format: 'status' },
    { key: 'teamName', label: t('pages.admin.feature_flags.team') },
    { key: 'action', label: t('pages.admin.feature_flags.action') },
    { key: 'reason', label: t('pages.admin.feature_flags.reason') },
    { key: 'actorPublicId', label: t('pages.admin.feature_flags.actor'), hidden: true },
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
    <Head :title="t('pages.admin.feature_flags.head_title')" />
    <AdminLayout :title="t('pages.admin.feature_flags.title')" :title-icon="IconFlag3">
        <PageStack>
            <MetricGrid :items="summaryItems" />

            <SurfaceCard
                :title="t('pages.admin.feature_flags.change_value')"
                :icon="IconFlag3"
                :subtitle="t('pages.admin.feature_flags.change_value_subtitle')"
            >
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <FormSelect v-model="form.flag_key" :label="t('pages.admin.feature_flags.flag')" :options="flagOptions" />
                        <FormSelect
                            v-model="form.scope"
                            :label="t('pages.admin.feature_flags.scope')"
                            :options="[
                                { value: 'team', label: t('pages.admin.feature_flags.selected_team') },
                                { value: 'global', label: t('pages.admin.feature_flags.global') },
                            ]"
                        />
                        <FormSelect
                            v-model="form.team_public_id"
                            :label="t('pages.admin.feature_flags.team')"
                            :options="teamOptions"
                            :button-class="form.scope === 'global' ? 'opacity-60' : ''"
                            @update:model-value="refreshForTeam"
                        />
                        <div class="flex items-end">
                            <FormCheckbox v-model="form.enabled" :label="t('pages.admin.feature_flags.enabled')" />
                        </div>
                    </div>
                    <FormInput
                        v-model="form.reason"
                        :label="t('pages.admin.feature_flags.reason')"
                        :error="form.errors.reason"
                        :placeholder="t('pages.admin.feature_flags.reason_placeholder')"
                    />
                    <div class="flex flex-wrap items-center gap-3">
                        <FormButton type="submit" :icon="IconFlag3" :loading="form.processing">
                            {{ t('pages.admin.feature_flags.save_value') }}
                        </FormButton>
                        <FormButton
                            v-if="canClearTeam"
                            type="button"
                            tone="neutral"
                            :icon="IconUsersGroup"
                            :loading="form.processing"
                            @click="clearTeam"
                        >
                            {{ t('pages.admin.feature_flags.clear_team_override') }}
                        </FormButton>
                    </div>
                    <p v-if="selectedFlag" class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{
                            t('pages.admin.feature_flags.editing_for', {
                                flag: selectedFlag.name,
                                target:
                                    form.scope === 'global'
                                        ? t('pages.admin.feature_flags.all_teams')
                                        : (selectedTeam?.name ?? t('pages.admin.feature_flags.selected_team_fallback')),
                            })
                        }}
                    </p>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.feature_flags.registered')"
                :rows="flags"
                :columns="flagColumns"
                row-key="key"
                state-key="admin.feature-flags.flags"
                export-key="admin.feature-flags.flags"
                :exports="exports"
                :filters="{ team: form.team_public_id }"
                :empty-label="t('pages.admin.feature_flags.empty_flags')"
            />

            <NoticeBanner :title="t('pages.admin.feature_flags.bounded_title')">
                {{ t('pages.admin.feature_flags.bounded_history') }}
            </NoticeBanner>

            <DataTable
                :title="t('pages.admin.feature_flags.recent_history')"
                :rows="history"
                :columns="historyColumns"
                row-key="publicId"
                state-key="admin.feature-flags.history"
                export-key="admin.feature-flags.history"
                :exports="exports"
                :empty-label="t('pages.admin.feature_flags.empty_history')"
            />
        </PageStack>
    </AdminLayout>
</template>
