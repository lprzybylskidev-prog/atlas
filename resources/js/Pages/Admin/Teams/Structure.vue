<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconChevronDown,
    IconChevronRight,
    IconGripVertical,
    IconHistory,
    IconSitemap,
    IconUserCog,
    IconUserPlus,
    IconUsers,
    IconUserX,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../../Components/Form/DialogFormActions.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import PageStack from '../../../Components/PageStack.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import UiState from '../../../Components/UiState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatDate } from '../../../Utils/formatters';

type StructuralRole = 'employee' | 'manager' | 'head_manager';

interface TeamMember extends FormSelectOption {
    value: string;
    name: string;
    email: string;
    structuralRole: StructuralRole;
}

interface ManagerRelationship {
    publicId: string;
    managerUserPublicId: string;
    managerName: string;
    managerEmail: string;
    reportUserPublicId: string;
    reportName: string;
    reportEmail: string;
    validFrom: string;
    reason: string;
}

interface MembershipHistoryRow {
    userPublicId: string;
    userName: string;
    userEmail: string;
    validFrom: string | null;
    validTo: string | null;
    structuralRole: StructuralRole;
    active: boolean;
}

interface StructuralRolePreview {
    allowed: boolean;
    currentRole: StructuralRole;
    targetRole: StructuralRole;
    endingRelationshipPublicIds: string[];
    affectedUserPublicIds: string[];
    warnings: string[];
}

const props = defineProps<{
    structureVersion: string;
    selectedTeamPublicId: string;
    teamOptions: FormSelectOption[];
    teamMembers: TeamMember[];
    activeRelationships: ManagerRelationship[];
    membershipHistory: MembershipHistoryRow[];
    assignableUsers: FormSelectOption[];
    structuralRolePreview: StructuralRolePreview | null;
}>();

const { locale, t } = useTranslator();
const today = new Date().toISOString().slice(0, 10);
const selectedTeam = ref(props.selectedTeamPublicId);
const expandedMemberIds = ref<string[]>([]);
const draggedMember = ref<TeamMember | null>(null);
const activeDropTarget = ref<string | null>(null);
const relationshipDialogOpen = ref(false);
const relationshipRemovalDialogOpen = ref(false);
const membershipRemovalDialogOpen = ref(false);
const roleDialogOpen = ref(false);
const rolePreviewLoading = ref(false);
const rolePreviewMemberId = ref<string | null>(null);
const selectedMember = ref<TeamMember | null>(null);
const selectedRelationship = ref<ManagerRelationship | null>(null);
const selectedMembershipRemoval = ref<TeamMember | null>(null);

const relationshipForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    manager_user_public_id: '',
    report_user_public_id: '',
    valid_from: today,
    reason: '',
    structure_version: props.structureVersion,
});
const roleForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    user_public_id: '',
    structural_role: 'employee' as StructuralRole,
    reason: '',
    structure_version: props.structureVersion,
});
const addMemberForm = useForm({ user_public_id: '' });
const relationshipRemovalForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    valid_to: today,
    reason: '',
    structure_version: props.structureVersion,
    operation: '',
});
const membershipRemovalForm = useForm({ reason: '', operation: '' });

const roleOrder: StructuralRole[] = ['head_manager', 'manager', 'employee'];
const membersByRole = computed<Record<StructuralRole, TeamMember[]>>(() => ({
    head_manager: props.teamMembers.filter((member) => member.structuralRole === 'head_manager'),
    manager: props.teamMembers.filter((member) => member.structuralRole === 'manager'),
    employee: props.teamMembers.filter((member) => member.structuralRole === 'employee'),
}));
const managerOptions = computed<FormSelectOption[]>(() =>
    membersByRole.value.manager.map((member) => ({ value: member.value, label: member.label })),
);
const roleOptions = computed<FormSelectOption[]>(() => roleOrder.map((role) => ({ value: role, label: roleLabel(role) })));
const backHref = computed(() => `/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/edit`);

watch(selectedTeam, (teamPublicId) => {
    if (teamPublicId === '' || teamPublicId === props.selectedTeamPublicId) return;
    router.get(`/admin/teams/${encodeURIComponent(teamPublicId)}/structure`);
});

watch(
    () => props.structureVersion,
    (version) => {
        relationshipForm.structure_version = version;
        roleForm.structure_version = version;
        relationshipRemovalForm.structure_version = version;
    },
);

function roleLabel(role: StructuralRole): string {
    const labels: Record<StructuralRole, string> = {
        employee: t('pages.admin.teams.structure.roles.employee'),
        manager: t('pages.admin.teams.structure.roles.manager'),
        head_manager: t('pages.admin.teams.structure.roles.head_manager'),
    };

    return labels[role];
}

function sectionTitle(role: StructuralRole): string {
    const labels: Record<StructuralRole, string> = {
        employee: t('pages.admin.teams.structure.sections.employee'),
        manager: t('pages.admin.teams.structure.sections.manager'),
        head_manager: t('pages.admin.teams.structure.sections.head_manager'),
    };

    return labels[role];
}

function sectionDescription(role: StructuralRole): string {
    const labels: Record<StructuralRole, string> = {
        employee: t('pages.admin.teams.structure.sections.employee_description'),
        manager: t('pages.admin.teams.structure.sections.manager_description'),
        head_manager: t('pages.admin.teams.structure.sections.head_manager_description'),
    };

    return labels[role];
}

function sectionEmptyTitle(role: StructuralRole): string {
    const labels: Record<StructuralRole, string> = {
        employee: t('pages.admin.teams.structure.sections.employee_empty'),
        manager: t('pages.admin.teams.structure.sections.manager_empty'),
        head_manager: t('pages.admin.teams.structure.sections.head_manager_empty'),
    };

    return labels[role];
}

function directReports(member: TeamMember): ManagerRelationship[] {
    return props.activeRelationships.filter((relationship) => relationship.managerUserPublicId === member.value);
}

function managersFor(member: TeamMember): ManagerRelationship[] {
    return props.activeRelationships.filter((relationship) => relationship.reportUserPublicId === member.value);
}

function isExpanded(member: TeamMember): boolean {
    return expandedMemberIds.value.includes(member.value);
}

function toggleMember(member: TeamMember): void {
    expandedMemberIds.value = isExpanded(member)
        ? expandedMemberIds.value.filter((publicId) => publicId !== member.value)
        : [...expandedMemberIds.value, member.value];
}

function openRelationshipDialog(report: TeamMember, manager?: TeamMember): void {
    relationshipForm.clearErrors();
    relationshipForm.reset();
    relationshipForm.team_public_id = props.selectedTeamPublicId;
    relationshipForm.report_user_public_id = report.value;
    relationshipForm.manager_user_public_id = manager?.value ?? '';
    relationshipForm.valid_from = today;
    relationshipForm.structure_version = props.structureVersion;
    selectedMember.value = report;
    relationshipDialogOpen.value = true;
}

function closeRelationshipDialog(): void {
    relationshipDialogOpen.value = false;
    relationshipForm.clearErrors();
}

function submitRelationship(): void {
    relationshipForm.post(`/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/relationships`, {
        preserveScroll: true,
        onSuccess: closeRelationshipDialog,
    });
}

function startDrag(member: TeamMember, event: DragEvent): void {
    if (member.structuralRole === 'head_manager') return;
    draggedMember.value = member;
    event.dataTransfer?.setData('text/plain', member.value);
    if (event.dataTransfer !== null) event.dataTransfer.effectAllowed = 'copy';
}

function finishDrag(): void {
    draggedMember.value = null;
    activeDropTarget.value = null;
}

function allowManagerDrop(manager: TeamMember, event: DragEvent): void {
    if (draggedMember.value === null || draggedMember.value.value === manager.value) return;
    event.preventDefault();
    activeDropTarget.value = manager.value;
    if (event.dataTransfer !== null) event.dataTransfer.dropEffect = 'copy';
}

function dropOnManager(manager: TeamMember, event: DragEvent): void {
    event.preventDefault();
    const report = draggedMember.value;
    finishDrag();
    if (report === null || report.value === manager.value || report.structuralRole === 'head_manager') return;
    openRelationshipDialog(report, manager);
}

function openRoleDialog(member: TeamMember): void {
    selectedMember.value = member;
    roleForm.clearErrors();
    roleForm.reset();
    roleForm.team_public_id = props.selectedTeamPublicId;
    roleForm.user_public_id = member.value;
    roleForm.structural_role = member.structuralRole;
    roleForm.structure_version = props.structureVersion;
    rolePreviewMemberId.value = null;
    roleDialogOpen.value = true;
}

function closeRoleDialog(): void {
    roleDialogOpen.value = false;
    roleForm.clearErrors();
}

function previewRoleChange(): void {
    if (selectedMember.value === null || roleForm.structural_role === selectedMember.value.structuralRole) return;
    rolePreviewLoading.value = true;
    router.get(
        `/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure`,
        { role_preview_user: selectedMember.value.value, role_preview_target: roleForm.structural_role },
        {
            only: ['structuralRolePreview'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => (rolePreviewMemberId.value = selectedMember.value?.value ?? null),
            onFinish: () => (rolePreviewLoading.value = false),
        },
    );
}

function submitRoleChange(): void {
    if (
        rolePreviewLoading.value ||
        rolePreviewMemberId.value !== selectedMember.value?.value ||
        props.structuralRolePreview === null ||
        props.structuralRolePreview.targetRole !== roleForm.structural_role ||
        !props.structuralRolePreview.allowed
    ) {
        return;
    }

    roleForm.patch(`/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/structural-role`, {
        preserveScroll: true,
        onSuccess: closeRoleDialog,
    });
}

function submitAddMember(): void {
    if (addMemberForm.user_public_id === '') return;
    addMemberForm.post(`/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/members`, { preserveScroll: true });
}

function openMembershipRemovalDialog(member: TeamMember): void {
    selectedMembershipRemoval.value = member;
    membershipRemovalForm.clearErrors();
    membershipRemovalForm.reset();
    membershipRemovalDialogOpen.value = true;
}

function closeMembershipRemovalDialog(): void {
    membershipRemovalDialogOpen.value = false;
    membershipRemovalForm.clearErrors();
    selectedMembershipRemoval.value = null;
}

function submitRemoveMember(): void {
    const member = selectedMembershipRemoval.value;
    if (member === null) return;

    membershipRemovalForm.delete(
        `/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/members/${encodeURIComponent(member.value)}`,
        {
            preserveScroll: true,
            onSuccess: closeMembershipRemovalDialog,
        },
    );
}

function openRelationshipRemovalDialog(relationship: ManagerRelationship): void {
    selectedRelationship.value = relationship;
    relationshipRemovalForm.clearErrors();
    relationshipRemovalForm.reset();
    relationshipRemovalForm.team_public_id = props.selectedTeamPublicId;
    relationshipRemovalForm.valid_to = today;
    relationshipRemovalForm.structure_version = props.structureVersion;
    relationshipRemovalDialogOpen.value = true;
}

function closeRelationshipRemovalDialog(): void {
    relationshipRemovalDialogOpen.value = false;
    relationshipRemovalForm.clearErrors();
    selectedRelationship.value = null;
}

function submitEnd(): void {
    const relationship = selectedRelationship.value;
    if (relationship === null) return;

    relationshipRemovalForm.patch(
        `/admin/teams/${encodeURIComponent(props.selectedTeamPublicId)}/structure/relationships/${encodeURIComponent(relationship.publicId)}/end`,
        {
            preserveScroll: true,
            onSuccess: closeRelationshipRemovalDialog,
        },
    );
}

function activeRelationshipCount(member: TeamMember): number {
    return directReports(member).length + managersFor(member).length;
}

function relationshipDate(value: string): string {
    return formatDate(value, locale.value);
}

function optionalDate(value: string | null): string {
    return value === null ? t('pages.admin.teams.structure.members.current') : relationshipDate(value);
}
</script>

<template>
    <Head :title="t('pages.admin.teams.structure.title')" />
    <AppLayout mode="admin" :title="t('pages.admin.teams.structure.title')" :title-icon="IconSitemap">
        <PageStack>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <ActionLink :href="backHref" :icon="IconArrowLeft">
                    {{ t('pages.admin.teams.structure.actions.back_to_managers') }}
                </ActionLink>
                <div class="w-full sm:w-72">
                    <FormSelect
                        v-model="selectedTeam"
                        :label="t('pages.admin.teams.structure.filters.team')"
                        :options="teamOptions"
                        :placeholder="t('pages.admin.teams.structure.filters.team_placeholder')"
                    />
                </div>
            </div>

            <UiState
                v-if="teamMembers.length === 0"
                variant="empty"
                :title="t('pages.admin.teams.structure.members.empty_title')"
                :description="t('pages.admin.teams.structure.members.empty_description')"
            />

            <section v-for="role in roleOrder" v-else :key="role" :aria-labelledby="`structure-section-${role}`" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 :id="`structure-section-${role}`" class="text-lg font-semibold text-zinc-950 dark:text-zinc-50">
                            {{ sectionTitle(role) }}
                        </h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ sectionDescription(role) }}
                        </p>
                    </div>
                    <StatusBadge :label="String(membersByRole[role].length)" :tone="role === 'head_manager' ? 'warning' : 'neutral'" />
                </div>

                <UiState v-if="membersByRole[role].length === 0" variant="empty" size="compact" :title="sectionEmptyTitle(role)" />

                <div v-else class="grid gap-3 xl:grid-cols-2">
                    <article
                        v-for="member in membersByRole[role]"
                        :key="member.value"
                        :data-testid="`team-structure-member-${member.value}`"
                        :data-structural-role="member.structuralRole"
                        :draggable="member.structuralRole !== 'head_manager'"
                        :class="[
                            'rounded-lg border bg-white shadow-sm transition dark:bg-zinc-950',
                            activeDropTarget === member.value
                                ? 'border-teal-500 ring-2 ring-teal-200 dark:border-teal-400 dark:ring-teal-900'
                                : 'border-zinc-200 dark:border-zinc-800',
                            member.structuralRole !== 'head_manager' ? 'cursor-grab active:cursor-grabbing' : '',
                        ]"
                        @dragstart="startDrag(member, $event)"
                        @dragend="finishDrag"
                        @dragover="member.structuralRole === 'manager' && allowManagerDrop(member, $event)"
                        @dragleave="activeDropTarget = null"
                        @drop="member.structuralRole === 'manager' && dropOnManager(member, $event)"
                    >
                        <div class="flex items-start gap-3 p-4">
                            <IconGripVertical
                                v-if="member.structuralRole !== 'head_manager'"
                                aria-hidden="true"
                                class="mt-1 hidden h-5 w-5 shrink-0 text-zinc-400 lg:block"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-zinc-950 dark:text-zinc-50">{{ member.name }}</p>
                                        <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ member.email }}</p>
                                    </div>
                                    <StatusBadge
                                        :label="roleLabel(member.structuralRole)"
                                        :tone="
                                            member.structuralRole === 'head_manager'
                                                ? 'warning'
                                                : member.structuralRole === 'manager'
                                                  ? 'info'
                                                  : 'neutral'
                                        "
                                    />
                                </div>

                                <p v-if="member.structuralRole === 'head_manager'" class="mt-3 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ t('pages.admin.teams.structure.cards.whole_team_scope') }}
                                </p>
                                <div v-else class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    <span v-if="member.structuralRole === 'manager'">
                                        {{
                                            t('pages.admin.teams.structure.cards.direct_reports_count', {
                                                count: directReports(member).length,
                                            })
                                        }}
                                    </span>
                                    <span>{{
                                        t('pages.admin.teams.structure.cards.managers_count', { count: managersFor(member).length })
                                    }}</span>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <FormButton type="button" tone="neutral" :icon="IconUserCog" @click="openRoleDialog(member)">
                                        {{ t('pages.admin.teams.structure.actions.change_role') }}
                                    </FormButton>
                                    <FormButton
                                        v-if="member.structuralRole !== 'head_manager'"
                                        type="button"
                                        tone="neutral"
                                        :icon="IconUserPlus"
                                        @click="openRelationshipDialog(member)"
                                    >
                                        {{ t('pages.admin.teams.structure.actions.assign_manager') }}
                                    </FormButton>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-200 dark:hover:bg-zinc-900"
                                        :aria-expanded="isExpanded(member)"
                                        :aria-controls="`member-details-${member.value}`"
                                        @click="toggleMember(member)"
                                    >
                                        <component
                                            :is="isExpanded(member) ? IconChevronDown : IconChevronRight"
                                            aria-hidden="true"
                                            class="h-4 w-4"
                                        />
                                        {{
                                            isExpanded(member)
                                                ? t('pages.admin.teams.structure.actions.collapse')
                                                : t('pages.admin.teams.structure.actions.expand')
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isExpanded(member)"
                            :id="`member-details-${member.value}`"
                            class="border-t border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/40"
                        >
                            <p v-if="member.structuralRole === 'head_manager'" class="text-sm text-zinc-600 dark:text-zinc-300">
                                {{ t('pages.admin.teams.structure.cards.head_manager_details') }}
                            </p>
                            <div v-else class="grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ t('pages.admin.teams.structure.cards.current_managers') }}
                                    </h3>
                                    <p v-if="managersFor(member).length === 0" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ t('pages.admin.teams.structure.cards.no_managers') }}
                                    </p>
                                    <ul v-else class="mt-2 space-y-2">
                                        <li v-for="relationship in managersFor(member)" :key="relationship.publicId" class="text-sm">
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ relationship.managerName }}</span>
                                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                                {{
                                                    t('pages.admin.teams.structure.cards.since', {
                                                        date: relationshipDate(relationship.validFrom),
                                                    })
                                                }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="member.structuralRole === 'manager'">
                                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ t('pages.admin.teams.structure.cards.direct_reports') }}
                                    </h3>
                                    <p v-if="directReports(member).length === 0" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ t('pages.admin.teams.structure.cards.no_direct_reports') }}
                                    </p>
                                    <div v-else class="mt-2 space-y-3">
                                        <SurfaceCard
                                            v-for="relationship in directReports(member)"
                                            :key="relationship.publicId"
                                            :data-testid="`manager-relationship-${relationship.publicId}`"
                                            :aria-label="relationship.reportName"
                                            :padded="false"
                                            body-class="p-3"
                                        >
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ relationship.reportName }}
                                            </p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ relationship.reason }}</p>
                                            <div class="mt-3">
                                                <FormButton
                                                    type="button"
                                                    tone="danger"
                                                    :icon="IconUserX"
                                                    @click="openRelationshipRemovalDialog(relationship)"
                                                >
                                                    {{ t('pages.admin.teams.structure.actions.end_report') }}
                                                </FormButton>
                                            </div>
                                        </SurfaceCard>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <FormButton type="button" tone="danger" :icon="IconUserX" @click="openMembershipRemovalDialog(member)">
                                    {{ t('pages.admin.teams.structure.members.end_action') }}
                                </FormButton>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.admin.teams.structure.members.add_title')" :icon="IconUserPlus" tone="teal">
                    <AtlasForm :processing="addMemberForm.processing" @submit="submitAddMember">
                        <FormSelect
                            v-model="addMemberForm.user_public_id"
                            :label="t('pages.admin.teams.structure.members.user')"
                            :options="assignableUsers"
                            :placeholder="t('pages.admin.teams.structure.members.user_placeholder')"
                            :error="addMemberForm.errors.user_public_id"
                        />
                        <FormActions>
                            <FormButton type="submit" :icon="IconUserPlus" :loading="addMemberForm.processing">
                                {{ t('pages.admin.teams.structure.members.add_action') }}
                            </FormButton>
                        </FormActions>
                    </AtlasForm>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.admin.teams.structure.members.history_title')" :icon="IconHistory" tone="zinc">
                    <details class="group">
                        <summary
                            class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-2 font-medium text-zinc-800 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-100"
                        >
                            <IconChevronRight aria-hidden="true" class="h-4 w-4 transition group-open:rotate-90" />
                            {{ t('pages.admin.teams.structure.members.history_title') }}
                        </summary>
                        <UiState
                            v-if="membershipHistory.length === 0"
                            variant="empty"
                            size="compact"
                            :title="t('pages.admin.teams.structure.members.history_empty')"
                        />
                        <div v-else class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                            <div
                                v-for="membership in membershipHistory"
                                :key="`${membership.userPublicId}-${membership.validFrom ?? 'unknown'}`"
                                class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                            >
                                <div class="flex flex-wrap justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ membership.userName }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ membership.userEmail }}</p>
                                    </div>
                                    <StatusBadge :label="roleLabel(membership.structuralRole)" tone="neutral" />
                                </div>
                                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ optionalDate(membership.validFrom) }} – {{ optionalDate(membership.validTo) }}
                                </p>
                            </div>
                        </div>
                    </details>
                </SurfaceCard>
            </div>
        </PageStack>

        <DialogPanel
            v-model:open="relationshipDialogOpen"
            :title="t('pages.admin.teams.structure.relationship_dialog.title')"
            :icon="IconUsers"
            tone="teal"
            :close-label="t('modal.cancel')"
            @close="closeRelationshipDialog"
        >
            <AtlasForm :processing="relationshipForm.processing" @submit="submitRelationship">
                <p class="mb-4 text-sm text-zinc-700 dark:text-zinc-200">
                    {{ t('pages.admin.teams.structure.relationship_dialog.description', { report: selectedMember?.name ?? '' }) }}
                </p>
                <div class="grid gap-4">
                    <FormSelect
                        v-model="relationshipForm.manager_user_public_id"
                        :label="t('pages.admin.teams.structure.forms.manager')"
                        :options="managerOptions.filter((option) => option.value !== relationshipForm.report_user_public_id)"
                        :error="relationshipForm.errors.manager_user_public_id"
                    />
                    <FormDateInput
                        v-model="relationshipForm.valid_from"
                        :label="t('pages.admin.teams.structure.forms.valid_from')"
                        :error="relationshipForm.errors.valid_from"
                    />
                    <FormTextarea
                        v-model="relationshipForm.reason"
                        :label="t('pages.admin.teams.structure.forms.reason')"
                        :placeholder="t('pages.admin.teams.structure.forms.assign_reason_placeholder')"
                        :error="relationshipForm.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.teams.structure.actions.confirm_relationship')"
                    :submit-icon="IconUserPlus"
                    :loading="relationshipForm.processing"
                    @cancel="closeRelationshipDialog"
                />
            </AtlasForm>
        </DialogPanel>

        <DialogPanel
            v-model:open="relationshipRemovalDialogOpen"
            :title="t('pages.admin.teams.structure.relationship_removal_dialog.title')"
            :icon="IconUserX"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeRelationshipRemovalDialog"
        >
            <AtlasForm :processing="relationshipRemovalForm.processing" @submit="submitEnd">
                <div v-if="selectedRelationship !== null" class="space-y-4">
                    <p class="text-sm text-zinc-700 dark:text-zinc-200">
                        {{
                            t('pages.admin.teams.structure.relationship_removal_dialog.description', {
                                manager: selectedRelationship.managerName,
                                report: selectedRelationship.reportName,
                            })
                        }}
                    </p>
                    <UiState
                        v-if="relationshipRemovalForm.errors.operation"
                        variant="error"
                        size="compact"
                        :title="t('pages.admin.teams.structure.feedback.relationship_removal_failed')"
                        :description="relationshipRemovalForm.errors.operation"
                    />
                    <FormDateInput
                        v-model="relationshipRemovalForm.valid_to"
                        :label="t('pages.admin.teams.structure.forms.valid_to')"
                        :error="relationshipRemovalForm.errors.valid_to"
                    />
                    <FormTextarea
                        v-model="relationshipRemovalForm.reason"
                        :label="t('pages.admin.teams.structure.forms.end_reason')"
                        :placeholder="t('pages.admin.teams.structure.forms.end_reason_placeholder')"
                        :error="relationshipRemovalForm.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.teams.structure.relationship_removal_dialog.confirm')"
                    :submit-icon="IconUserX"
                    submit-tone="danger"
                    :loading="relationshipRemovalForm.processing"
                    @cancel="closeRelationshipRemovalDialog"
                />
            </AtlasForm>
        </DialogPanel>

        <DialogPanel
            v-model:open="membershipRemovalDialogOpen"
            :title="t('pages.admin.teams.structure.membership_removal_dialog.title')"
            :icon="IconUserX"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeMembershipRemovalDialog"
        >
            <AtlasForm :processing="membershipRemovalForm.processing" @submit="submitRemoveMember">
                <div v-if="selectedMembershipRemoval !== null" class="space-y-4">
                    <div>
                        <p class="font-semibold text-zinc-950 dark:text-zinc-50">{{ selectedMembershipRemoval.name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ selectedMembershipRemoval.email }}</p>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-200">
                        {{
                            t('pages.admin.teams.structure.membership_removal_dialog.impact', {
                                count: activeRelationshipCount(selectedMembershipRemoval),
                            })
                        }}
                    </p>
                    <UiState
                        v-if="membershipRemovalForm.errors.operation"
                        variant="error"
                        size="compact"
                        :title="t('pages.admin.teams.structure.feedback.membership_removal_failed')"
                        :description="membershipRemovalForm.errors.operation"
                    />
                    <FormTextarea
                        v-model="membershipRemovalForm.reason"
                        :label="t('pages.admin.teams.structure.members.end_reason')"
                        :placeholder="t('pages.admin.teams.structure.members.end_reason_placeholder')"
                        :error="membershipRemovalForm.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.teams.structure.membership_removal_dialog.confirm')"
                    :submit-icon="IconUserX"
                    submit-tone="danger"
                    :loading="membershipRemovalForm.processing"
                    @cancel="closeMembershipRemovalDialog"
                />
            </AtlasForm>
        </DialogPanel>

        <DialogPanel
            v-model:open="roleDialogOpen"
            :title="t('pages.admin.teams.structure.role_dialog.title')"
            :icon="IconUserCog"
            tone="amber"
            :close-label="t('modal.cancel')"
            @close="closeRoleDialog"
        >
            <AtlasForm :processing="roleForm.processing" @submit="submitRoleChange">
                <p class="mb-4 font-medium text-zinc-950 dark:text-zinc-50">{{ selectedMember?.name }}</p>
                <div class="grid gap-4">
                    <FormSelect
                        v-model="roleForm.structural_role"
                        :label="t('pages.admin.teams.structure.role_dialog.target_role')"
                        :options="roleOptions"
                        :error="roleForm.errors.structural_role"
                        @update:model-value="previewRoleChange"
                    />
                    <section
                        v-if="
                            structuralRolePreview !== null &&
                            structuralRolePreview.targetRole === roleForm.structural_role &&
                            rolePreviewMemberId === selectedMember?.value
                        "
                        data-testid="structural-role-impact-preview"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <h3 class="font-semibold">{{ t('pages.admin.teams.structure.preview.title') }}</h3>
                        <p class="mt-2 text-sm">
                            {{
                                t('pages.admin.teams.structure.role_dialog.transition', {
                                    from: roleLabel(structuralRolePreview.currentRole),
                                    to: roleLabel(structuralRolePreview.targetRole),
                                })
                            }}
                        </p>
                        <p class="mt-1 text-sm">
                            {{
                                t('pages.admin.teams.structure.role_dialog.relationships_ending', {
                                    count: structuralRolePreview.endingRelationshipPublicIds.length,
                                })
                            }}
                        </p>
                        <ul v-if="structuralRolePreview.warnings.length > 0" class="mt-2 list-disc space-y-1 pl-5 text-sm">
                            <li v-for="warning in structuralRolePreview.warnings" :key="warning">{{ warning }}</li>
                        </ul>
                    </section>
                    <FormTextarea
                        v-model="roleForm.reason"
                        :label="t('pages.admin.teams.structure.forms.reason')"
                        :placeholder="t('pages.admin.teams.structure.role_dialog.reason_placeholder')"
                        :error="roleForm.errors.reason ?? roleForm.errors.user_public_id"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.teams.structure.actions.confirm_role')"
                    :submit-icon="IconUserCog"
                    :loading="roleForm.processing"
                    :disabled="
                        rolePreviewLoading ||
                        rolePreviewMemberId !== selectedMember?.value ||
                        structuralRolePreview === null ||
                        structuralRolePreview.targetRole !== roleForm.structural_role ||
                        !structuralRolePreview.allowed ||
                        roleForm.reason.trim() === ''
                    "
                    @cancel="closeRoleDialog"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
