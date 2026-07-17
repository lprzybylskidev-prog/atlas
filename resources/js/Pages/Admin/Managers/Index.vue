<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowRight, IconChevronDown, IconGitBranch, IconSitemap, IconUserStar, IconUsers } from '@tabler/icons-vue';
import { computed, reactive, ref, watch } from 'vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatDateTime } from '../../../Utils/formatters';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

interface ImpactPreview {
    allowed: boolean;
    action: string;
    affectedReportPublicIds: string[];
    warnings: string[];
}

interface RelationshipRow {
    publicId: string;
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
    endPreview: ImpactPreview;
}

interface HierarchyNode {
    userPublicId: string;
    name: string;
    email: string;
    headManager: boolean;
    reports: HierarchyNode[];
}

interface TeamMember extends FormSelectOption {
    name: string;
    email: string;
    headManager: boolean;
}

interface FlatNode extends HierarchyNode {
    depth: number;
}

const props = defineProps<{
    selectedTeamPublicId: string;
    teamOptions: FormSelectOption[];
    userOptions: FormSelectOption[];
    teamMembers: TeamMember[];
    relationships: RelationshipRow[];
    history: RelationshipRow[];
    tree: HierarchyNode[];
    preview: ImpactPreview | null;
}>();

const { t } = useTranslator('en');
const today = new Date().toISOString().slice(0, 10);
const selectedRelationship = ref<string | null>(null);
const showHistory = ref(false);

const filterForm = useForm({
    team: props.selectedTeamPublicId,
});
const assignForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    manager_user_public_id: '',
    report_user_public_id: '',
    valid_from: today,
    reason: '',
});
const headForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    user_public_id: '',
    reason: '',
});
const endForms = reactive<Record<string, { valid_to: string; reason: string }>>({});
const removeHeadForms = reactive<Record<string, { reason: string }>>({});

watch(
    () => props.selectedTeamPublicId,
    (team) => {
        filterForm.team = team;
        assignForm.team_public_id = team;
        headForm.team_public_id = team;
        selectedRelationship.value = null;
    },
);

watch(
    () => headForm.user_public_id,
    (userPublicId) => {
        if (userPublicId !== '') {
            headForm.clearErrors('user_public_id');
        }
    },
);

const memberOptions = computed<FormSelectOption[]>(() => props.teamMembers.map(({ value, label }) => ({ value, label })));
const headManagers = computed(() => props.teamMembers.filter((member) => member.headManager));
const selectedHeadMember = computed(() => props.teamMembers.find((member) => String(member.value) === headForm.user_public_id) ?? null);
const canPreview = computed(
    () => assignForm.team_public_id !== '' && assignForm.manager_user_public_id !== '' && assignForm.report_user_public_id !== '',
);
const createBlocked = computed(() => props.preview !== null && !props.preview.allowed);

const flatTree = computed<FlatNode[]>(() => {
    const rows: FlatNode[] = [];
    const visit = (node: HierarchyNode, depth: number): void => {
        rows.push({ ...node, depth });
        node.reports.forEach((child) => visit(child, depth + 1));
    };

    props.tree.forEach((node) => visit(node, 0));

    return rows;
});

function applyTeamFilter(): void {
    router.get('/admin/managers', { team: filterForm.team }, { preserveState: false, preserveScroll: true, replace: true });
}

function previewRelationship(): void {
    if (!canPreview.value) {
        return;
    }

    router.get(
        '/admin/managers',
        {
            team: assignForm.team_public_id,
            preview_manager: assignForm.manager_user_public_id,
            preview_report: assignForm.report_user_public_id,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function submitRelationship(): void {
    assignForm.post('/admin/managers', { preserveScroll: true });
}

function selectHeadMember(member: TeamMember): void {
    headForm.user_public_id = String(member.value);
}

function addHeadManager(): void {
    headForm
        .transform((data) => ({
            ...data,
            head_manager: true,
        }))
        .patch('/admin/managers/head', { preserveScroll: true });
}

function removeHeadForm(userPublicId: string): { reason: string } {
    removeHeadForms[userPublicId] ??= { reason: '' };

    return removeHeadForms[userPublicId];
}

function removeHeadManager(member: TeamMember): void {
    const userPublicId = String(member.value);
    const values = removeHeadForm(userPublicId);

    router.patch(
        '/admin/managers/head',
        {
            team_public_id: props.selectedTeamPublicId,
            user_public_id: userPublicId,
            head_manager: false,
            reason: values.reason,
        },
        { preserveScroll: true },
    );
}

function endForm(publicId: string): { valid_to: string; reason: string } {
    endForms[publicId] ??= { valid_to: today, reason: '' };

    return endForms[publicId];
}

function chooseRelationship(relationship: RelationshipRow): void {
    selectedRelationship.value = selectedRelationship.value === relationship.publicId ? null : relationship.publicId;
}

function endRelationship(relationship: RelationshipRow): void {
    const values = endForm(relationship.publicId);

    router.patch(
        `/admin/managers/${relationship.publicId}/end`,
        {
            team_public_id: props.selectedTeamPublicId,
            valid_to: values.valid_to,
            reason: values.reason,
        },
        { preserveScroll: true },
    );
}

function formattedDate(value: string | null): string {
    return value === null ? 'Active' : formatDateTime(value, 'en-US');
}
</script>

<template>
    <Head :title="t('pages.admin.managers.head_title')" />
    <AdminLayout :title="t('pages.admin.managers.title')" :title-icon="IconSitemap">
        <section class="space-y-5">
            <section class="grid gap-3 lg:grid-cols-[minmax(20rem,1.35fr)_repeat(3,minmax(10rem,0.55fr))]">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <FormSelect
                        v-model="filterForm.team"
                        label="Team"
                        :options="[{ value: '', label: 'Select team' }, ...teamOptions]"
                        placeholder="Select team"
                    />
                    <FormButton type="button" class="mt-3" @click="applyTeamFilter">Load team</FormButton>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Members</p>
                        <IconUsers aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ teamMembers.length }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Relationships</p>
                        <IconGitBranch aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ relationships.length }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Heads</p>
                        <IconUserStar aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ headManagers.length }}</p>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.65fr)]">
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <IconGitBranch aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Current hierarchy</h2>
                        </div>
                        <StatusBadge :value="flatTree.length > 0" true-label="Active" false-label="Empty" />
                    </div>

                    <div
                        v-if="flatTree.length === 0"
                        class="mt-5 rounded-lg border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                    >
                        No active hierarchy for this team.
                    </div>
                    <div v-else class="mt-5 space-y-2">
                        <div
                            v-for="node in flatTree"
                            :key="`${node.userPublicId}-${node.depth}`"
                            class="grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-3 dark:border-zinc-800 dark:bg-zinc-900/50 sm:grid-cols-[minmax(0,1fr)_auto]"
                            :style="{ marginLeft: `${node.depth * 1.25}rem` }"
                        >
                            <div class="min-w-0">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span
                                        class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                    >
                                        L{{ node.depth }}
                                    </span>
                                    <p class="truncate font-medium text-zinc-950 dark:text-zinc-50">{{ node.name }}</p>
                                </div>
                                <p class="mt-1 break-all text-xs text-zinc-500 dark:text-zinc-400">{{ node.email }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ node.reports.length }} reports</span>
                                <StatusBadge :value="node.headManager" true-label="Head" false-label="Manager" />
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="space-y-5">
                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex items-center gap-2">
                            <IconArrowRight aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">New relationship</h2>
                        </div>
                        <AtlasForm class="mt-4 space-y-4" :processing="assignForm.processing" @submit="submitRelationship">
                            <FormSelect
                                v-model="assignForm.manager_user_public_id"
                                label="Manager"
                                :options="[{ value: '', label: 'Select manager' }, ...memberOptions]"
                                :error="assignForm.errors.manager_user_public_id"
                            />
                            <FormSelect
                                v-model="assignForm.report_user_public_id"
                                label="Direct report"
                                :options="[{ value: '', label: 'Select report' }, ...memberOptions]"
                                :error="assignForm.errors.report_user_public_id"
                            />
                            <FormDateInput v-model="assignForm.valid_from" label="Valid from" :error="assignForm.errors.valid_from" />
                            <FormTextarea v-model="assignForm.reason" label="Reason" :error="assignForm.errors.reason" />

                            <div v-if="preview" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Preview</p>
                                    <StatusBadge :value="preview.allowed" true-label="Allowed" false-label="Blocked" />
                                </div>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    Affected reports: {{ preview.affectedReportPublicIds.length }}
                                </p>
                                <ul
                                    v-if="preview.warnings.length > 0"
                                    class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-700 dark:text-rose-300"
                                >
                                    <li v-for="warning in preview.warnings" :key="warning">{{ warning }}</li>
                                </ul>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <FormButton type="button" :disabled="!canPreview" @click="previewRelationship">Preview</FormButton>
                                <FormButton type="submit" :loading="assignForm.processing" :disabled="createBlocked">Create</FormButton>
                            </div>
                        </AtlasForm>
                    </section>

                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex items-center gap-2">
                            <IconUserStar aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Head managers</h2>
                        </div>
                        <AtlasForm class="mt-4 space-y-4" :processing="headForm.processing" @submit="addHeadManager">
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <FormSelect
                                    v-model="headForm.user_public_id"
                                    label="Member"
                                    :options="[{ value: '', label: 'Select member' }, ...memberOptions]"
                                    :error="headForm.errors.user_public_id"
                                />
                                <FormButton type="submit" :loading="headForm.processing" :disabled="headForm.user_public_id === ''">
                                    Add
                                </FormButton>
                            </div>
                            <div v-if="selectedHeadMember" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-zinc-950 dark:text-zinc-50">{{ selectedHeadMember.name }}</p>
                                        <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ selectedHeadMember.email }}</p>
                                    </div>
                                    <StatusBadge :value="selectedHeadMember.headManager" true-label="Head" false-label="Member" />
                                </div>
                            </div>
                            <FormTextarea v-model="headForm.reason" label="Reason" :error="headForm.errors.reason" />
                        </AtlasForm>

                        <div
                            class="mt-4 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                        >
                            <div v-if="headManagers.length === 0" class="p-3 text-sm text-zinc-500 dark:text-zinc-400">
                                No head managers set.
                            </div>
                            <template v-else>
                                <div v-for="member in headManagers" :key="member.value" class="grid gap-3 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <button type="button" class="min-w-0 text-left" @click="selectHeadMember(member)">
                                            <span class="block truncate font-medium text-zinc-950 dark:text-zinc-50">{{
                                                member.name
                                            }}</span>
                                            <span class="block break-all text-xs text-zinc-500 dark:text-zinc-400">{{ member.email }}</span>
                                        </button>
                                        <StatusBadge :value="true" true-label="Head" />
                                    </div>
                                    <AtlasForm class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" @submit="removeHeadManager(member)">
                                        <FormTextarea
                                            v-model="removeHeadForm(String(member.value)).reason"
                                            label="Remove reason"
                                            :rows="2"
                                        />
                                        <FormButton type="submit" class="mt-0 sm:mt-6">Remove</FormButton>
                                    </AtlasForm>
                                </div>
                            </template>
                        </div>
                    </section>
                </aside>
            </div>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Active relationships</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ relationships.length }} active</p>
                </div>
                <div v-if="relationships.length === 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">No active relationships.</div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2">Manager</th>
                                <th class="px-3 py-2">Direct report</th>
                                <th class="px-3 py-2">Valid from</th>
                                <th class="px-3 py-2">Impact</th>
                                <th class="px-3 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <template v-for="relationship in relationships" :key="relationship.publicId">
                                <tr>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ relationship.managerName }}</p>
                                        <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ relationship.managerEmail }}</p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ relationship.reportName }}</p>
                                        <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ relationship.reportEmail }}</p>
                                    </td>
                                    <td class="px-3 py-3">{{ formattedDate(relationship.validFrom) }}</td>
                                    <td class="px-3 py-3">{{ relationship.endPreview.affectedReportPublicIds.length }} affected</td>
                                    <td class="px-3 py-3">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                            @click="chooseRelationship(relationship)"
                                        >
                                            <IconChevronDown aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                            End
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="selectedRelationship === relationship.publicId">
                                    <td class="bg-zinc-50 px-3 py-3 dark:bg-zinc-900/60" colspan="5">
                                        <AtlasForm
                                            class="grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)_auto]"
                                            @submit="endRelationship(relationship)"
                                        >
                                            <FormDateInput v-model="endForm(relationship.publicId).valid_to" label="Valid to" />
                                            <FormTextarea v-model="endForm(relationship.publicId).reason" label="Reason" :rows="2" />
                                            <FormButton type="submit" class="mt-0 md:mt-6">End relationship</FormButton>
                                        </AtlasForm>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 text-left"
                    :aria-expanded="showHistory"
                    @click="showHistory = !showHistory"
                >
                    <span class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Relationship history</span>
                    <span class="inline-flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ history.length }} records
                        <IconChevronDown
                            aria-hidden="true"
                            class="h-4 w-4 transition"
                            :class="showHistory ? 'rotate-180' : ''"
                            :stroke-width="1.8"
                        />
                    </span>
                </button>
                <div v-if="showHistory" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="px-3 py-2">Manager</th>
                                <th class="px-3 py-2">Direct report</th>
                                <th class="px-3 py-2">Valid from</th>
                                <th class="px-3 py-2">Valid to</th>
                                <th class="px-3 py-2">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="history.length === 0">
                                <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400" colspan="5">No relationship history.</td>
                            </tr>
                            <tr v-for="relationship in history" :key="`history-${relationship.publicId}`">
                                <td class="px-3 py-3">{{ relationship.managerName }}</td>
                                <td class="px-3 py-3">{{ relationship.reportName }}</td>
                                <td class="px-3 py-3">{{ formattedDate(relationship.validFrom) }}</td>
                                <td class="px-3 py-3">{{ formattedDate(relationship.validTo) }}</td>
                                <td class="px-3 py-3">
                                    {{ relationship.validTo === null ? relationship.reason : relationship.endReason }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>
