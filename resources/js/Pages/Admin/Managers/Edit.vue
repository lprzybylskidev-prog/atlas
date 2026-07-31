<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconDeviceFloppy, IconGitBranch, IconSitemap, IconStar, IconUserPlus, IconUserX } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import ManagerHierarchyTree, { type ManagerHierarchyNode } from '../../../Components/Managers/ManagerHierarchyTree.vue';
import PageStack from '../../../Components/PageStack.vue';
import SearchableCheckboxList from '../../../Components/SearchableCheckboxList.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import TextBadge from '../../../Components/TextBadge.vue';
import UiState from '../../../Components/UiState.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatDate } from '../../../Utils/formatters';
import type { CheckboxListOption } from '../../../Components/CheckboxList.vue';

interface Option {
    value: string;
    label: string;
}

interface TeamMember extends Option {
    name: string;
    email: string;
    headManager: boolean;
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

interface EndDraft {
    valid_to: string;
    reason: string;
    processing: boolean;
}

const props = defineProps<{
    selectedTeamPublicId: string;
    teamOptions: Option[];
    manager: ManagerRow;
    teamMembers: TeamMember[];
    relationships: ManagerRelationship[];
    tree: ManagerHierarchyNode[];
    previewReportPublicIds: string[];
    assignmentPreviews: AssignmentPreview[];
}>();

const { locale, t } = useTranslator();
const today = new Date().toISOString().slice(0, 10);
const assignForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    manager_user_public_id: props.manager.userPublicId,
    report_user_public_ids: [...props.previewReportPublicIds],
    valid_from: today,
    reason: '',
});
const headForm = useForm({
    team_public_id: props.selectedTeamPublicId,
    user_public_id: props.manager.userPublicId,
    head_manager: props.manager.managerType === 'head' ? 'true' : 'false',
    reason: '',
});
const endDrafts = reactive<Record<string, EndDraft>>(
    Object.fromEntries(
        props.relationships.map((relationship) => [relationship.publicId, { valid_to: today, reason: '', processing: false }]),
    ),
);

const activeReportIds = computed(() => new Set(props.relationships.map((relationship) => relationship.reportUserPublicId)));
const reportOptions = computed<CheckboxListOption[]>(() =>
    props.teamMembers
        .filter((member) => member.value !== props.manager.userPublicId && !activeReportIds.value.has(member.value))
        .map((member) => ({
            value: member.value,
            label: member.name,
            description: member.email,
        })),
);
const selectedReportsLabel = computed(() =>
    t('pages.admin.managers.forms.selected_reports', {
        selected: assignForm.report_user_public_ids.length,
        total: reportOptions.value.length,
    }),
);
const previewAllowedCount = computed(() => props.assignmentPreviews.filter((preview) => preview.allowed).length);
const previewBlockedCount = computed(() => props.assignmentPreviews.length - previewAllowedCount.value);
const previewAffectedCount = computed(() => new Set(props.assignmentPreviews.flatMap((preview) => preview.affectedReportPublicIds)).size);
const headOptions = computed<FormSelectOption[]>(() => [
    { value: 'true', label: t('pages.admin.managers.forms.head_enable') },
    { value: 'false', label: t('pages.admin.managers.forms.head_disable') },
]);
const backHref = computed(() => `/admin/managers?team=${encodeURIComponent(props.selectedTeamPublicId)}`);
const canPreviewAssignments = computed(() => assignForm.report_user_public_ids.length > 0);
const canAssignReports = computed(
    () => assignForm.report_user_public_ids.length > 0 && assignForm.valid_from !== '' && assignForm.reason.trim() !== '',
);

function submitAssign(): void {
    assignForm.post('/admin/managers', { preserveScroll: true });
}

function previewAssign(): void {
    if (!canPreviewAssignments.value) {
        return;
    }

    const query = new URLSearchParams({ team: props.selectedTeamPublicId });

    for (const reportUserPublicId of assignForm.report_user_public_ids) {
        query.append('preview_reports[]', reportUserPublicId);
    }

    router.get(
        `/admin/managers/${encodeURIComponent(props.manager.userPublicId)}/edit?${query.toString()}`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function submitHead(): void {
    headForm
        .transform((data) => ({ ...data, head_manager: data.head_manager === 'true' }))
        .patch('/admin/managers/head', { preserveScroll: true });
}

function submitEnd(relationship: ManagerRelationship): void {
    const draft = endDrafts[relationship.publicId];

    if (draft === undefined || draft.reason.trim() === '') {
        return;
    }

    draft.processing = true;
    router.patch(
        `/admin/managers/${encodeURIComponent(relationship.publicId)}/end`,
        {
            team_public_id: props.selectedTeamPublicId,
            valid_to: draft.valid_to,
            reason: draft.reason,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                draft.processing = false;
            },
        },
    );
}

function relationshipDate(value: string): string {
    return formatDate(value, locale.value);
}
</script>

<template>
    <Head :title="t('pages.admin.managers.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.managers.edit.title', { manager: manager.name })" :title-icon="IconSitemap">
        <PageStack>
            <div class="flex justify-start">
                <ActionLink :href="backHref" :icon="IconArrowLeft">
                    {{ t('pages.admin.managers.actions.back_to_managers') }}
                </ActionLink>
            </div>

            <SurfaceCard :title="manager.name" :subtitle="manager.email" :icon="IconSitemap" tone="teal">
                <div class="grid gap-3 text-sm text-zinc-700 md:grid-cols-4 dark:text-zinc-200">
                    <div>
                        <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.managers.table.team') }}</div>
                        <div class="mt-1">{{ manager.teamName }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managers.table.manager_type') }}
                        </div>
                        <TextBadge
                            class="mt-1"
                            :label="
                                manager.managerType === 'head'
                                    ? t('pages.admin.managers.tree.head_manager')
                                    : t('pages.admin.managers.tree.manager')
                            "
                            :tone="manager.managerType === 'head' ? 'warning' : 'info'"
                        />
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managers.table.direct_reports_count') }}
                        </div>
                        <div class="mt-1">{{ manager.directReportsCount }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.managers.table.subtree_reports_count') }}
                        </div>
                        <div class="mt-1">{{ manager.subtreeReportsCount }}</div>
                    </div>
                </div>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(25rem,0.9fr)]">
                <ManagerHierarchyTree :nodes="tree" />

                <div class="space-y-4">
                    <SurfaceCard :title="t('pages.admin.managers.forms.add_reports_title')" :icon="IconUserPlus" tone="teal">
                        <AtlasForm :processing="assignForm.processing" @submit="submitAssign">
                            <div class="grid gap-4">
                                <SearchableCheckboxList
                                    v-model="assignForm.report_user_public_ids"
                                    :options="reportOptions"
                                    :label="t('pages.admin.managers.forms.reports')"
                                    :search-label="t('pages.admin.managers.forms.report_search')"
                                    :search-placeholder="t('pages.admin.managers.forms.report_search_placeholder')"
                                    :selected-label="selectedReportsLabel"
                                    :empty-text="t('pages.admin.managers.forms.no_available_reports')"
                                    :error="assignForm.errors.report_user_public_ids"
                                    :item-monospace="false"
                                    max-height="max-h-72"
                                />

                                <div class="grid gap-3 md:grid-cols-2">
                                    <FormDateInput
                                        v-model="assignForm.valid_from"
                                        :label="t('pages.admin.managers.forms.valid_from')"
                                        :error="assignForm.errors.valid_from"
                                    />
                                    <FormTextarea
                                        v-model="assignForm.reason"
                                        :label="t('pages.admin.managers.forms.reason')"
                                        :placeholder="t('pages.admin.managers.forms.assign_reason_placeholder')"
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
                                                {{ t('pages.admin.managers.preview.allowed') }}
                                            </p>
                                            <p class="mt-1 font-semibold">{{ previewAllowedCount }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                                {{ t('pages.admin.managers.preview.blocked') }}
                                            </p>
                                            <p class="mt-1 font-semibold">{{ previewBlockedCount }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                                {{ t('pages.admin.managers.preview.affected') }}
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
                                                    <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ preview.reportName }}</p>
                                                    <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ preview.reportEmail }}
                                                    </p>
                                                </div>
                                                <TextBadge
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
                                        {{ t('pages.admin.managers.actions.preview') }}
                                    </FormButton>
                                    <FormButton
                                        type="submit"
                                        :loading="assignForm.processing"
                                        :disabled="!canAssignReports"
                                        :icon="IconDeviceFloppy"
                                    >
                                        {{ t('pages.admin.managers.actions.assign_selected') }}
                                    </FormButton>
                                </FormActions>
                            </div>
                        </AtlasForm>
                    </SurfaceCard>

                    <SurfaceCard :title="t('pages.admin.managers.forms.head_title')" :icon="IconStar" tone="amber">
                        <AtlasForm :processing="headForm.processing" @submit="submitHead">
                            <div class="grid gap-3">
                                <FormSelect
                                    v-model="headForm.head_manager"
                                    :label="t('pages.admin.managers.forms.head_state')"
                                    :options="headOptions"
                                />
                                <FormTextarea
                                    v-model="headForm.reason"
                                    :label="t('pages.admin.managers.forms.reason')"
                                    :placeholder="t('pages.admin.managers.forms.head_reason_placeholder')"
                                    :rows="3"
                                    :error="headForm.errors.reason"
                                />
                                <FormActions>
                                    <FormButton type="submit" :loading="headForm.processing" :icon="IconStar">
                                        {{ t('pages.admin.managers.actions.update_head') }}
                                    </FormButton>
                                </FormActions>
                            </div>
                        </AtlasForm>
                    </SurfaceCard>
                </div>
            </div>

            <SurfaceCard :title="t('pages.admin.managers.forms.direct_reports_title')" :icon="IconUserX" tone="rose">
                <UiState
                    v-if="relationships.length === 0"
                    variant="empty"
                    size="compact"
                    :title="t('pages.admin.managers.empty.direct_reports_title')"
                    :description="t('pages.admin.managers.empty.direct_reports_description')"
                />

                <div v-else class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    <div
                        v-for="relationship in relationships"
                        :key="relationship.publicId"
                        class="grid gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)_auto]"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ relationship.reportName }}</p>
                            <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ relationship.reportEmail }}</p>
                            <div class="mt-3 grid gap-2 text-xs text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                                    <p class="font-semibold text-zinc-500 dark:text-zinc-400">
                                        {{ t('pages.admin.managers.table.valid_from') }}
                                    </p>
                                    <p class="mt-1">{{ relationshipDate(relationship.validFrom) }}</p>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-900/50">
                                    <p class="font-semibold text-zinc-500 dark:text-zinc-400">
                                        {{ t('pages.admin.managers.table.reason') }}
                                    </p>
                                    <p class="mt-1 break-words">{{ relationship.reason }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <FormDateInput
                                v-model="endDrafts[relationship.publicId].valid_to"
                                :label="t('pages.admin.managers.forms.valid_to')"
                            />
                            <FormInput
                                v-model="endDrafts[relationship.publicId].reason"
                                :label="t('pages.admin.managers.forms.end_reason')"
                                :placeholder="t('pages.admin.managers.forms.end_reason_placeholder')"
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
                            {{ t('pages.admin.managers.actions.end_report') }}
                        </FormButton>
                    </div>
                </div>
            </SurfaceCard>
        </PageStack>
    </AdminLayout>
</template>
