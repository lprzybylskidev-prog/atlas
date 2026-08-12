<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconArrowsExchange,
    IconDeviceFloppy,
    IconGitBranch,
    IconHistory,
    IconSitemap,
    IconStar,
    IconUserPlus,
    IconUsersGroup,
    IconUserX,
} from '@tabler/icons-vue';
import { computed, reactive, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import ManagerHierarchyTree, { type ManagerHierarchyNode } from '../../../Components/Managers/ManagerHierarchyTree.vue';
import PageStack from '../../../Components/PageStack.vue';
import SearchableCheckboxList from '../../../Components/SearchableCheckboxList.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import UiState from '../../../Components/UiState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatDate } from '../../../Utils/formatters';
import type { CheckboxListOption } from '../../../Components/CheckboxList.vue';

interface TeamMember extends FormSelectOption {
    value: string;
    name: string;
    email: string;
    headManager: boolean;
    manager: boolean;
}

interface ManagerRow extends Record<string, unknown> {
    userPublicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    email: string;
    managerType: string;
    directReportsCount: number;
    subtreeReportsCount: number;
}

interface ManagerRelationship extends Record<string, unknown> {
    publicId: string;
    teamPublicId: string;
    teamName: string;
    managerUserPublicId: string;
    managerName: string;
    managerEmail: string;
    reportUserPublicId: string;
    reportName: string;
    reportEmail: string;
    validFrom: string;
    validTo: string | null;
    reason: string;
    endReason: string | null;
}

interface AssignmentPreview {
    reportUserPublicId: string;
    reportName: string;
    reportEmail: string;
    allowed: boolean;
    affectedReportPublicIds: string[];
    warnings: string[];
}

interface MembershipHistoryRow {
    userPublicId: string;
    userName: string;
    userEmail: string;
    validFrom: string | null;
    validTo: string | null;
    headManager: boolean;
    active: boolean;
}

interface EndDraft {
    valid_to: string;
    reason: string;
    processing: boolean;
}

interface MemberRemovalDraft {
    reason: string;
    processing: boolean;
}

interface ReparentDraft {
    new_manager_user_public_id: string;
    move_date: string;
    reason: string;
    processing: boolean;
}

const props = defineProps<{
    structureVersion: string;
    selectedTeamPublicId: string;
    selectedManagerPublicId: string;
    teamOptions: FormSelectOption[];
    teamMembers: TeamMember[];
    membershipHistory: MembershipHistoryRow[];
    assignableUsers: FormSelectOption[];
    manager: ManagerRow | null;
    relationships: ManagerRelationship[];
    tree: ManagerHierarchyNode[];
    previewReportPublicIds: string[];
    assignmentPreviews: AssignmentPreview[];
}>();

const { locale, t } = useTranslator();
const today = new Date().toISOString().slice(0, 10);
const context = useForm({
    team_public_id: props.selectedTeamPublicId,
    manager_user_public_id: props.selectedManagerPublicId,
});
const assignForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    manager_user_public_id: props.selectedManagerPublicId,
    report_user_public_ids: [...props.previewReportPublicIds],
    valid_from: today,
    reason: '',
    structure_version: props.structureVersion,
});
const headForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    user_public_id: props.selectedManagerPublicId,
    head_manager: props.manager?.managerType === 'head' ? 'true' : 'false',
    reason: '',
    structure_version: props.structureVersion,
});
const endDrafts = reactive<Record<string, EndDraft>>(
    Object.fromEntries(
        props.relationships.map((relationship) => [relationship.publicId, { valid_to: today, reason: '', processing: false }]),
    ),
);
const addMemberForm = useForm({ user_public_id: '' });
const memberRemovalDrafts = reactive<Record<string, MemberRemovalDraft>>(
    Object.fromEntries(props.teamMembers.map((member) => [member.value, { reason: '', processing: false }])),
);
const reparentDrafts = reactive<Record<string, ReparentDraft>>(
    Object.fromEntries(
        props.relationships.map((relationship) => [
            relationship.publicId,
            { new_manager_user_public_id: '', move_date: today, reason: '', processing: false },
        ]),
    ),
);

const managerOptions = computed<FormSelectOption[]>(() =>
    props.teamMembers.map((member) => ({
        value: member.value,
        label: member.label,
        badge: member.headManager
            ? { label: t('pages.admin.teams.structure.tree.head_manager'), tone: 'warning' }
            : member.manager
              ? { label: t('pages.admin.teams.structure.tree.manager'), tone: 'info' }
              : undefined,
    })),
);
const activeReportIds = computed(() => new Set(props.relationships.map((relationship) => relationship.reportUserPublicId)));
const reportOptions = computed<CheckboxListOption[]>(() =>
    props.teamMembers
        .filter((member) => member.value !== context.manager_user_public_id && !activeReportIds.value.has(member.value))
        .map((member) => ({
            value: member.value,
            label: member.name,
            description: member.email,
        })),
);
const selectedReportsLabel = computed(() =>
    t('pages.admin.teams.structure.forms.selected_reports', {
        selected: assignForm.report_user_public_ids.length,
        total: reportOptions.value.length,
    }),
);
const previewAllowedCount = computed(() => props.assignmentPreviews.filter((preview) => preview.allowed).length);
const previewBlockedCount = computed(() => props.assignmentPreviews.length - previewAllowedCount.value);
const previewAffectedCount = computed(() => new Set(props.assignmentPreviews.flatMap((preview) => preview.affectedReportPublicIds)).size);
const headOptions = computed<FormSelectOption[]>(() => [
    { value: 'true', label: t('pages.admin.teams.structure.forms.head_enable') },
    { value: 'false', label: t('pages.admin.teams.structure.forms.head_disable') },
]);
const contextMatchesLoadedManager = computed(
    () =>
        props.manager !== null &&
        context.team_public_id === props.selectedTeamPublicId &&
        context.manager_user_public_id === props.selectedManagerPublicId,
);
const canLoadManager = computed(() => context.team_public_id === props.selectedTeamPublicId && context.manager_user_public_id !== '');
const backHref = computed(() =>
    context.team_public_id === '' ? '/admin/teams' : `/admin/teams/${encodeURIComponent(context.team_public_id)}/edit`,
);
const canPreviewAssignments = computed(() => contextMatchesLoadedManager.value && assignForm.report_user_public_ids.length > 0);
const canAssignReports = computed(
    () =>
        contextMatchesLoadedManager.value &&
        assignForm.report_user_public_ids.length > 0 &&
        assignForm.valid_from !== '' &&
        assignForm.reason.trim() !== '',
);

watch(
    () => context.team_public_id,
    (teamPublicId) => {
        if (teamPublicId === '' || teamPublicId === props.selectedTeamPublicId) {
            return;
        }

        context.manager_user_public_id = '';
        router.get(
            `/admin/teams/${encodeURIComponent(teamPublicId)}/structure`,
            {},
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    },
);

watch(
    () => props.selectedTeamPublicId,
    (teamPublicId) => {
        context.team_public_id = teamPublicId;
        assignForm.team_public_id = teamPublicId;
        headForm.team_public_id = teamPublicId;
    },
);

watch(
    () => props.selectedManagerPublicId,
    (managerUserPublicId) => {
        context.manager_user_public_id = managerUserPublicId;
        assignForm.manager_user_public_id = managerUserPublicId;
        headForm.user_public_id = managerUserPublicId;
    },
);

watch(
    () => props.teamMembers,
    (members) => {
        for (const member of members) {
            memberRemovalDrafts[member.value] ??= { reason: '', processing: false };
        }
    },
    { deep: true },
);

watch(
    () => props.relationships,
    (relationships) => {
        for (const relationship of relationships) {
            endDrafts[relationship.publicId] ??= { valid_to: today, reason: '', processing: false };
            reparentDrafts[relationship.publicId] ??= {
                new_manager_user_public_id: '',
                move_date: today,
                reason: '',
                processing: false,
            };
        }
    },
    { deep: true },
);

function refreshContext(): void {
    const query: Record<string, string> = {};

    if (context.team_public_id !== '') {
        query.team = context.team_public_id;
    }

    if (context.manager_user_public_id !== '') {
        query.preview_manager = context.manager_user_public_id;
    }

    router.get(`/admin/teams/${encodeURIComponent(context.team_public_id)}/structure`, query, {
        preserveScroll: true,
        preserveState: false,
    });
}

function submitAssign(): void {
    if (!contextMatchesLoadedManager.value) {
        return;
    }

    assignForm.team_public_id = context.team_public_id;
    assignForm.manager_user_public_id = context.manager_user_public_id;
    assignForm.post(`/admin/teams/${encodeURIComponent(context.team_public_id)}/structure/relationships`, { preserveScroll: true });
}

function previewAssign(): void {
    if (!canPreviewAssignments.value) {
        return;
    }

    const query = new URLSearchParams({
        team: context.team_public_id,
        preview_manager: context.manager_user_public_id,
    });

    for (const reportUserPublicId of assignForm.report_user_public_ids) {
        query.append('preview_reports[]', reportUserPublicId);
    }

    router.get(
        `/admin/teams/${encodeURIComponent(context.team_public_id)}/structure?${query.toString()}`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function submitHead(): void {
    if (!contextMatchesLoadedManager.value) {
        return;
    }

    headForm.team_public_id = context.team_public_id;
    headForm.user_public_id = context.manager_user_public_id;
    headForm
        .transform((data) => ({ ...data, head_manager: data.head_manager === 'true' }))
        .patch(`/admin/teams/${encodeURIComponent(context.team_public_id)}/structure/head-manager`, { preserveScroll: true });
}

function submitEnd(relationship: ManagerRelationship): void {
    const draft = endDrafts[relationship.publicId];

    if (draft === undefined || draft.reason.trim() === '') {
        return;
    }

    draft.processing = true;
    router.patch(
        `/admin/teams/${encodeURIComponent(context.team_public_id)}/structure/relationships/${encodeURIComponent(relationship.publicId)}/end`,
        {
            team_public_id: context.team_public_id,
            valid_to: draft.valid_to,
            reason: draft.reason,
            structure_version: props.structureVersion,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                draft.processing = false;
            },
        },
    );
}

function submitAddMember(): void {
    if (addMemberForm.user_public_id === '') return;
    addMemberForm.post(`/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/members`, { preserveScroll: true });
}

function submitRemoveMember(member: TeamMember): void {
    const draft = memberRemovalDrafts[member.value];
    if (draft === undefined || draft.reason.trim() === '') return;
    draft.processing = true;
    router.delete(`/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/members/${encodeURIComponent(member.value)}`, {
        data: { reason: draft.reason },
        preserveScroll: true,
        onFinish: () => (draft.processing = false),
    });
}

function reparentOptions(relationship: ManagerRelationship): FormSelectOption[] {
    return props.teamMembers
        .filter((member) => member.value !== relationship.reportUserPublicId && member.value !== relationship.managerUserPublicId)
        .map((member) => ({ value: member.value, label: member.label }));
}

function submitReparent(relationship: ManagerRelationship): void {
    const draft = reparentDrafts[relationship.publicId];
    if (draft === undefined || draft.new_manager_user_public_id === '' || draft.reason.trim() === '') return;
    draft.processing = true;
    router.patch(
        `/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/relationships/${encodeURIComponent(relationship.publicId)}/reparent`,
        {
            new_manager_user_public_id: draft.new_manager_user_public_id,
            effective_at: draft.move_date,
            reason: draft.reason,
            structure_version: props.structureVersion,
        },
        { preserveScroll: true, onFinish: () => (draft.processing = false) },
    );
}

function relationshipDate(value: string): string {
    return formatDate(value, locale.value);
}

function optionalDate(value: string | null): string {
    return value === null ? t('pages.admin.teams.structure.members.current') : relationshipDate(value);
}
</script>

<template>
    <Head :title="t('pages.admin.teams.structure.create.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.teams.structure.create.title')" :title-icon="IconUserPlus">
        <PageStack>
            <div class="flex justify-start">
                <ActionLink :href="backHref" :icon="IconArrowLeft">
                    {{ t('pages.admin.teams.structure.actions.back_to_managers') }}
                </ActionLink>
            </div>

            <SurfaceCard :title="t('pages.admin.teams.structure.forms.context_title')" :icon="IconSitemap" tone="teal">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <FormSelect
                        v-model="context.team_public_id"
                        :label="t('pages.admin.teams.structure.filters.team')"
                        :options="teamOptions"
                        :placeholder="t('pages.admin.teams.structure.filters.team_placeholder')"
                    />
                    <FormSelect
                        v-model="context.manager_user_public_id"
                        :label="t('glossary.user.plural')"
                        :options="managerOptions"
                        :placeholder="t('pages.admin.teams.structure.forms.user_placeholder')"
                        :disabled="context.team_public_id !== props.selectedTeamPublicId"
                    />
                    <FormButton type="button" class="mt-0 lg:mt-6" :icon="IconSitemap" :disabled="!canLoadManager" @click="refreshContext">
                        {{ t('pages.admin.teams.structure.actions.load_manager') }}
                    </FormButton>
                </div>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
                <SurfaceCard :title="t('pages.admin.teams.structure.members.active_title')" :icon="IconUsersGroup" tone="sky">
                    <UiState
                        v-if="teamMembers.length === 0"
                        variant="empty"
                        size="compact"
                        :title="t('pages.admin.teams.structure.members.empty_title')"
                        :description="t('pages.admin.teams.structure.members.empty_description')"
                    />
                    <div v-else class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <div
                            v-for="member in teamMembers"
                            :key="member.value"
                            :data-testid="`team-member-${member.value}`"
                            class="grid gap-3 py-3 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.7fr)_auto]"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ member.name }}</p>
                                <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ member.email }}</p>
                                <StatusBadge
                                    v-if="member.headManager"
                                    class="mt-2"
                                    :label="t('pages.admin.teams.structure.tree.head_manager')"
                                    tone="warning"
                                />
                            </div>
                            <FormInput
                                v-model="memberRemovalDrafts[member.value].reason"
                                :label="t('pages.admin.teams.structure.members.end_reason')"
                                :placeholder="t('pages.admin.teams.structure.members.end_reason_placeholder')"
                            />
                            <FormButton
                                type="button"
                                tone="danger"
                                class="mt-0 lg:mt-6"
                                :icon="IconUserX"
                                :loading="memberRemovalDrafts[member.value].processing"
                                :disabled="memberRemovalDrafts[member.value].reason.trim() === ''"
                                @click="submitRemoveMember(member)"
                            >
                                {{ t('pages.admin.teams.structure.members.end_action') }}
                            </FormButton>
                        </div>
                    </div>
                </SurfaceCard>

                <div class="space-y-4">
                    <SurfaceCard :title="t('pages.admin.teams.structure.members.add_title')" :icon="IconUserPlus" tone="teal">
                        <AtlasForm :processing="addMemberForm.processing" @submit="submitAddMember">
                            <div class="grid gap-3">
                                <FormSelect
                                    v-model="addMemberForm.user_public_id"
                                    :label="t('pages.admin.teams.structure.members.user')"
                                    :options="assignableUsers"
                                    :placeholder="t('pages.admin.teams.structure.members.user_placeholder')"
                                    :error="addMemberForm.errors.user_public_id"
                                />
                                <FormActions>
                                    <FormButton
                                        type="submit"
                                        :icon="IconUserPlus"
                                        :loading="addMemberForm.processing"
                                        :disabled="addMemberForm.user_public_id === ''"
                                    >
                                        {{ t('pages.admin.teams.structure.members.add_action') }}
                                    </FormButton>
                                </FormActions>
                            </div>
                        </AtlasForm>
                    </SurfaceCard>

                    <details class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                        <summary
                            class="flex cursor-pointer items-center gap-2 font-semibold text-zinc-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600 dark:text-zinc-50"
                        >
                            <IconHistory class="size-5" aria-hidden="true" />
                            {{ t('pages.admin.teams.structure.members.history_title') }}
                        </summary>
                        <div class="mt-4 space-y-3">
                            <UiState
                                v-if="membershipHistory.length === 0"
                                variant="empty"
                                size="compact"
                                :title="t('pages.admin.teams.structure.members.history_empty')"
                            />
                            <div
                                v-for="(membership, index) in membershipHistory"
                                v-else
                                :key="`${membership.userPublicId}-${membership.validFrom}-${index}`"
                                class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ membership.userName }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ membership.userEmail }}</p>
                                    </div>
                                    <StatusBadge
                                        :label="
                                            membership.active
                                                ? t('pages.admin.teams.structure.members.active')
                                                : t('pages.admin.teams.structure.members.ended')
                                        "
                                        :tone="membership.active ? 'success' : 'neutral'"
                                    />
                                </div>
                                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ optionalDate(membership.validFrom) }} – {{ optionalDate(membership.validTo) }}
                                </p>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <UiState
                v-if="manager === null || !contextMatchesLoadedManager"
                variant="empty"
                :title="t('pages.admin.teams.structure.empty.create_context_title')"
                :description="t('pages.admin.teams.structure.empty.create_context_description')"
            />

            <template v-else>
                <SurfaceCard :title="manager.name" :subtitle="manager.email" :icon="IconSitemap" tone="teal">
                    <div class="grid gap-3 text-sm text-zinc-700 md:grid-cols-4 dark:text-zinc-200">
                        <div>
                            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.teams.structure.table.team') }}
                            </div>
                            <div class="mt-1">{{ manager.teamName }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.teams.structure.table.manager_type') }}
                            </div>
                            <StatusBadge
                                class="mt-1"
                                :label="
                                    manager.managerType === 'head'
                                        ? t('pages.admin.teams.structure.tree.head_manager')
                                        : t('pages.admin.teams.structure.tree.manager')
                                "
                                :tone="manager.managerType === 'head' ? 'warning' : 'info'"
                            />
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.teams.structure.table.direct_reports_count') }}
                            </div>
                            <div class="mt-1">{{ manager.directReportsCount }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.teams.structure.table.subtree_reports_count') }}
                            </div>
                            <div class="mt-1">{{ manager.subtreeReportsCount }}</div>
                        </div>
                    </div>
                </SurfaceCard>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(25rem,0.9fr)]">
                    <ManagerHierarchyTree :nodes="tree" />

                    <div class="space-y-4">
                        <SurfaceCard :title="t('pages.admin.teams.structure.forms.add_reports_title')" :icon="IconUserPlus" tone="teal">
                            <AtlasForm :processing="assignForm.processing" @submit="submitAssign">
                                <div class="grid gap-4">
                                    <SearchableCheckboxList
                                        v-model="assignForm.report_user_public_ids"
                                        :options="reportOptions"
                                        :label="t('pages.admin.teams.structure.forms.reports')"
                                        :search-label="t('pages.admin.teams.structure.forms.report_search')"
                                        :search-placeholder="t('pages.admin.teams.structure.forms.report_search_placeholder')"
                                        :selected-label="selectedReportsLabel"
                                        :empty-text="t('pages.admin.teams.structure.forms.no_available_reports')"
                                        :error="assignForm.errors.report_user_public_ids"
                                        :item-monospace="false"
                                        max-height="max-h-72"
                                    />

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <FormDateInput
                                            v-model="assignForm.valid_from"
                                            :label="t('pages.admin.teams.structure.forms.valid_from')"
                                            :error="assignForm.errors.valid_from"
                                        />
                                        <FormTextarea
                                            v-model="assignForm.reason"
                                            :label="t('pages.admin.teams.structure.forms.reason')"
                                            :placeholder="t('pages.admin.teams.structure.forms.assign_reason_placeholder')"
                                            :rows="3"
                                            :error="assignForm.errors.reason"
                                        />
                                    </div>

                                    <section
                                        v-if="assignmentPreviews.length > 0"
                                        class="rounded-lg border border-sky-200 bg-sky-50 p-3 dark:border-sky-900 dark:bg-sky-950/40"
                                    >
                                        <div class="grid gap-3 text-sm text-sky-950 md:grid-cols-3 dark:text-sky-100">
                                            <div>
                                                <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                                    {{ t('pages.admin.teams.structure.preview.allowed') }}
                                                </p>
                                                <p class="mt-1 font-semibold">{{ previewAllowedCount }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                                    {{ t('pages.admin.teams.structure.preview.blocked') }}
                                                </p>
                                                <p class="mt-1 font-semibold">{{ previewBlockedCount }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                                    {{ t('pages.admin.teams.structure.preview.affected') }}
                                                </p>
                                                <p class="mt-1 font-semibold">{{ previewAffectedCount }}</p>
                                            </div>
                                        </div>

                                        <div class="mt-3 space-y-2">
                                            <div
                                                v-for="preview in assignmentPreviews"
                                                :key="preview.reportUserPublicId"
                                                class="rounded-lg border border-white/70 bg-white p-3 text-sm dark:border-sky-900/60 dark:bg-zinc-950"
                                            >
                                                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">
                                                            {{ preview.reportName }}
                                                        </p>
                                                        <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ preview.reportEmail }}
                                                        </p>
                                                    </div>
                                                    <StatusBadge
                                                        :label="preview.allowed ? t('datatable.boolean.yes') : t('datatable.boolean.no')"
                                                        :tone="preview.allowed ? 'success' : 'danger'"
                                                    />
                                                </div>
                                                <ul
                                                    v-if="preview.warnings.length > 0"
                                                    class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-700 dark:text-rose-300"
                                                >
                                                    <li v-for="warning in preview.warnings" :key="warning">{{ warning }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </section>

                                    <FormActions>
                                        <FormButton
                                            type="button"
                                            tone="neutral"
                                            :disabled="!canPreviewAssignments"
                                            :icon="IconGitBranch"
                                            @click="previewAssign"
                                        >
                                            {{ t('pages.admin.teams.structure.actions.preview') }}
                                        </FormButton>
                                        <FormButton
                                            type="submit"
                                            :loading="assignForm.processing"
                                            :disabled="!canAssignReports"
                                            :icon="IconDeviceFloppy"
                                        >
                                            {{ t('pages.admin.teams.structure.actions.assign_selected') }}
                                        </FormButton>
                                    </FormActions>
                                </div>
                            </AtlasForm>
                        </SurfaceCard>

                        <SurfaceCard
                            data-testid="head-manager-card"
                            :title="t('pages.admin.teams.structure.forms.head_title')"
                            :icon="IconStar"
                            tone="amber"
                        >
                            <AtlasForm :processing="headForm.processing" @submit="submitHead">
                                <div class="grid gap-3">
                                    <FormSelect
                                        v-model="headForm.head_manager"
                                        :label="t('pages.admin.teams.structure.forms.head_state')"
                                        :options="headOptions"
                                    />
                                    <FormTextarea
                                        v-model="headForm.reason"
                                        :label="t('pages.admin.teams.structure.forms.reason')"
                                        :placeholder="t('pages.admin.teams.structure.forms.head_reason_placeholder')"
                                        :rows="3"
                                        :error="headForm.errors.reason"
                                    />
                                    <FormActions>
                                        <FormButton type="submit" :loading="headForm.processing" :icon="IconStar">
                                            {{ t('pages.admin.teams.structure.actions.update_head') }}
                                        </FormButton>
                                    </FormActions>
                                </div>
                            </AtlasForm>
                        </SurfaceCard>
                    </div>
                </div>

                <SurfaceCard :title="t('pages.admin.teams.structure.forms.direct_reports_title')" :icon="IconUserX" tone="rose">
                    <UiState
                        v-if="relationships.length === 0"
                        variant="empty"
                        size="compact"
                        :title="t('pages.admin.teams.structure.empty.direct_reports_title')"
                        :description="t('pages.admin.teams.structure.empty.direct_reports_description')"
                    />

                    <div
                        v-else
                        class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                    >
                        <div
                            v-for="relationship in relationships"
                            :key="relationship.publicId"
                            :data-testid="`manager-relationship-${relationship.publicId}`"
                            class="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)_auto]"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ relationship.reportName }}</p>
                                <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ relationship.reportEmail }}</p>
                                <div class="mt-3 grid gap-2 text-xs text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
                                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                                        <p class="font-semibold text-zinc-500 dark:text-zinc-400">
                                            {{ t('pages.admin.teams.structure.table.valid_from') }}
                                        </p>
                                        <p class="mt-1">{{ relationshipDate(relationship.validFrom) }}</p>
                                    </div>
                                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                                        <p class="font-semibold text-zinc-500 dark:text-zinc-400">
                                            {{ t('pages.admin.teams.structure.table.reason') }}
                                        </p>
                                        <p class="mt-1 wrap-break-word">{{ relationship.reason }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3">
                                <FormSelect
                                    v-model="reparentDrafts[relationship.publicId].new_manager_user_public_id"
                                    :label="t('pages.admin.teams.structure.move.new_manager')"
                                    :options="reparentOptions(relationship)"
                                    :placeholder="t('pages.admin.teams.structure.move.new_manager_placeholder')"
                                />
                                <FormDateInput
                                    v-model="reparentDrafts[relationship.publicId].move_date"
                                    :label="t('pages.admin.teams.structure.move.effective_at')"
                                />
                                <FormInput
                                    v-model="reparentDrafts[relationship.publicId].reason"
                                    :label="t('pages.admin.teams.structure.move.reason')"
                                    :placeholder="t('pages.admin.teams.structure.move.reason_placeholder')"
                                />
                                <FormButton
                                    type="button"
                                    tone="neutral"
                                    :icon="IconArrowsExchange"
                                    :loading="reparentDrafts[relationship.publicId].processing"
                                    :disabled="
                                        reparentDrafts[relationship.publicId].new_manager_user_public_id === '' ||
                                        reparentDrafts[relationship.publicId].reason.trim() === ''
                                    "
                                    @click="submitReparent(relationship)"
                                >
                                    {{ t('pages.admin.teams.structure.move.action') }}
                                </FormButton>
                                <FormDateInput
                                    v-model="endDrafts[relationship.publicId].valid_to"
                                    :label="t('pages.admin.teams.structure.forms.valid_to')"
                                />
                                <FormInput
                                    v-model="endDrafts[relationship.publicId].reason"
                                    :label="t('pages.admin.teams.structure.forms.end_reason')"
                                    :placeholder="t('pages.admin.teams.structure.forms.end_reason_placeholder')"
                                />
                            </div>

                            <FormButton
                                type="button"
                                tone="danger"
                                class="mt-0 xl:mt-6"
                                :loading="endDrafts[relationship.publicId].processing"
                                :disabled="endDrafts[relationship.publicId].reason.trim() === ''"
                                :icon="IconUserX"
                                @click="submitEnd(relationship)"
                            >
                                {{ t('pages.admin.teams.structure.actions.end_report') }}
                            </FormButton>
                        </div>
                    </div>
                </SurfaceCard>
            </template>
        </PageStack>
    </AppLayout>
</template>
