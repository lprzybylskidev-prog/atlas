<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconBriefcase,
    IconCalendarTime,
    IconCircleCheck,
    IconCircleOff,
    IconCircleX,
    IconDatabase,
    IconNotes,
    IconShieldCheck,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../Components/Form/DialogFormActions.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import {
    adminDetailIcon,
    adminDetailSubnavigation,
    detailFields,
    detailValue,
    recordMap,
    sectionRows,
    type DetailSection,
    type SummaryItem,
} from '../../Composables/useTimeTrackingAdminDetailUi';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { AtlasPageProps } from '../../Types/inertia';

interface DetailAction {
    key: 'approve_other_work' | 'force_close_other_work' | 'reject_other_work';
    label: string;
    icon: Component;
    tone: 'danger' | 'success' | 'warning';
}

const props = defineProps<{
    surface?: 'admin' | 'manager';
    kind: string;
    title: string;
    backHref: string;
    record: SummaryItem[];
    sections: DetailSection[];
}>();

const { locale, t } = useTranslator();
const page = usePage<AtlasPageProps>();
const surface = computed(() => props.surface ?? 'admin');
const isManagerSurface = computed(() => surface.value === 'manager');
const pageTitle = computed(() => t(props.title));
const values = computed(() => recordMap(props.record));
const auditRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.audit'));
const overviewFields = computed(() =>
    detailFields(
        ['user_name', 'user_email', 'team_name', 'category_label', 'approval_status', 'requires_manager_review', 'closure_reason'],
        values.value,
        t,
        locale.value,
    ),
);
const timingFields = computed(() => detailFields(['started_at', 'ended_at', 'exact_seconds'], values.value, t, locale.value));
const noteFields = computed(() =>
    detailFields(['description', 'end_note'], values.value, t, locale.value).filter((field) => field.formattedValue !== '-'),
);
const technicalFields = computed(() =>
    detailFields(
        ['public_id', 'work_session_public_id', 'category_key', 'user_public_id', 'team_public_id', 'created_at', 'updated_at'],
        values.value,
        t,
        locale.value,
    ),
);
const form = useForm({
    decision: '',
    reason: '',
});
const selectedAction = ref<DetailAction | null>(null);
const actionModalOpen = computed({
    get: () => selectedAction.value !== null,
    set: (open: boolean) => {
        if (!open) {
            closeActionModal();
        }
    },
});
const detailActions = computed<DetailAction[]>(() => {
    const publicId = values.value.public_id ?? '';
    const actions: DetailAction[] = [];
    const isActive = (values.value.ended_at ?? '') === '';
    const requiresDecision = (values.value.requires_manager_review ?? '') === 'true' && !isActive;

    if (publicId === '') {
        return [];
    }

    if (isActive && canUseRoute('admin.work-time.other-work.force-close', 'manager.work-time.other-work.force-close')) {
        actions.push({
            key: 'force_close_other_work',
            label: t('pages.time_tracking.admin_operations.actions.force_close'),
            icon: IconCircleOff,
            tone: 'danger',
        });
    }

    if (requiresDecision && canUseRoute('admin.work-time.other-work.decide', 'manager.work-time.other-work.decide')) {
        actions.push({
            key: 'approve_other_work',
            label: t('pages.time_tracking.admin_operations.actions.approve'),
            icon: IconCircleCheck,
            tone: 'success',
        });
        actions.push({
            key: 'reject_other_work',
            label: t('pages.time_tracking.admin_operations.actions.reject'),
            icon: IconCircleX,
            tone: 'danger',
        });
    }

    return actions;
});
const actionTitle = computed(() => {
    if (selectedAction.value?.key === 'approve_other_work') {
        return t('pages.time_tracking.admin_operations.dialog.approve_other_work');
    }

    if (selectedAction.value?.key === 'reject_other_work') {
        return t('pages.time_tracking.admin_operations.dialog.reject_other_work');
    }

    return t('pages.time_tracking.admin_operations.dialog.force_close_other_work');
});

function canUseAdminRoute(routeName: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(routeName);
}

function canUseRoute(adminRouteName: string, managerRouteName: string): boolean {
    return isManagerSurface.value
        ? page.props.auth.availableApplicationRoutes.includes(managerRouteName)
        : canUseAdminRoute(adminRouteName);
}

function openAction(action: DetailAction): void {
    selectedAction.value = action;
    form.defaults({
        decision: action.key === 'approve_other_work' ? 'approve' : action.key === 'reject_other_work' ? 'reject' : '',
        reason: '',
    });
    form.reset();
    form.clearErrors();
}

function closeActionModal(): void {
    selectedAction.value = null;
    form.reset();
    form.clearErrors();
}

function submitAction(): void {
    if (selectedAction.value === null || values.value.public_id === '') {
        return;
    }

    const endpoint =
        selectedAction.value.key === 'force_close_other_work'
            ? `${isManagerSurface.value ? '/manager' : '/admin'}/work-time/other-work/${values.value.public_id}/force-close`
            : `${isManagerSurface.value ? '/manager' : '/admin'}/work-time/other-work/${values.value.public_id}/decide`;

    form.post(endpoint, {
        preserveScroll: true,
        onSuccess: closeActionModal,
    });
}

function actionButtonClass(action: DetailAction): string {
    if (action.tone === 'success') {
        return 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950';
    }

    return 'border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950';
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout
        :title="pageTitle"
        :title-icon="adminDetailIcon('other_work')"
        :mode="surface"
        :subnavigation="isManagerSurface ? [] : adminDetailSubnavigation('other_work', t)"
        :subnavigation-label="t('navigation.group.work_time')"
    >
        <PageStack>
            <div>
                <ActionLink :href="backHref" :icon="IconArrowLeft">
                    {{ t('pages.time_tracking.admin_detail.back') }}
                </ActionLink>
            </div>

            <SurfaceCard
                :title="t('pages.time_tracking.admin_detail.actions.title')"
                :icon="IconBriefcase"
                tone="amber"
                v-if="detailActions.length > 0"
            >
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="action in detailActions"
                        :key="action.key"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition"
                        :class="actionButtonClass(action)"
                        @click="openAction(action)"
                    >
                        <component :is="action.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ action.label }}
                    </button>
                </div>
            </SurfaceCard>

            <SurfaceCard :title="pageTitle" :subtitle="t('navigation.work_time_other_work')" :icon="IconBriefcase" tone="teal">
                <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300"
                            >
                                <IconBriefcase aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            </div>
                            <div>
                                <div class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ values.user_name || pageTitle }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ values.team_name }}</div>
                            </div>
                            <StatusBadge
                                v-if="values.approval_status"
                                :value="values.approval_status"
                                :label="detailValue('approval_status', values.approval_status, t, locale)"
                            />
                        </div>
                        <dl class="grid gap-3 md:grid-cols-2">
                            <div v-for="field in overviewFields" :key="field.key">
                                <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ field.label }}</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ field.formattedValue }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="grid gap-3">
                        <div
                            v-for="field in timingFields"
                            :key="field.key"
                            class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                        >
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                <IconCalendarTime aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                {{ field.label }}
                            </div>
                            <div class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ field.formattedValue }}</div>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <SurfaceCard
                :title="t('pages.time_tracking.admin_detail.sections.notes')"
                :icon="IconNotes"
                tone="zinc"
                v-if="noteFields.length > 0"
            >
                <dl class="grid gap-3 md:grid-cols-2">
                    <div v-for="field in noteFields" :key="field.key" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ field.label }}</dt>
                        <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-zinc-950 dark:text-zinc-50">
                            {{ field.formattedValue }}
                        </dd>
                    </div>
                </dl>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.audit')" :icon="IconShieldCheck" tone="zinc">
                <div v-if="auditRows.length > 0" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <div
                        v-for="(row, index) in auditRows"
                        :key="row.public_id || index"
                        class="grid gap-2 py-3 md:grid-cols-[14rem_1fr_14rem]"
                    >
                        <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                            {{ detailValue('action', row.action, t, locale) }}
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ row.reason || '-' }}</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 md:text-right">
                            {{ detailValue('occurred_at', row.occurred_at, t, locale) }}
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('pages.time_tracking.admin_detail.empty_section') }}</div>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.technical')" :icon="IconDatabase" tone="zinc">
                <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="field in technicalFields"
                        :key="field.key"
                        class="min-w-0 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                    >
                        <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ field.label }}</dt>
                        <dd class="mt-1 break-words text-sm text-zinc-950 dark:text-zinc-50">{{ field.formattedValue }}</dd>
                    </div>
                </dl>
            </SurfaceCard>
        </PageStack>

        <DialogPanel
            v-model:open="actionModalOpen"
            :title="actionTitle"
            :icon="IconBriefcase"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeActionModal"
        >
            <AtlasForm :processing="form.processing" @submit="submitAction">
                <FormTextarea
                    v-model="form.reason"
                    :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                    :error="form.errors.reason"
                />
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.time_tracking.admin_operations.dialog.submit')"
                    :submit-icon="IconBriefcase"
                    submit-tone="danger"
                    :loading="form.processing"
                    @cancel="closeActionModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
