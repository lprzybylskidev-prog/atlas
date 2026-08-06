<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconCalendarTime,
    IconCircleX,
    IconFilePencil,
    IconHistory,
    IconNotes,
    IconShieldCheck,
    IconTool,
    IconUser,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../Components/Form/DialogFormActions.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import {
    adminDetailIcon,
    adminDetailSubnavigation,
    detailFieldLabel,
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
const form = useForm({
    decision: '',
    reason: '',
    final_started_at: '',
    final_ended_at: '',
});
const selectedAction = ref<'reject' | 'correct' | ''>('');
const pageTitle = computed(() => t(props.title));
const values = computed(() => recordMap(props.record));
const proposal = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.proposals')[0] ?? {});
const availableActions = computed(() => (values.value.available_actions ?? '').split(',').filter((action) => action !== ''));
const actionModalOpen = computed({
    get: () => selectedAction.value !== '',
    set: (open: boolean) => {
        if (!open) {
            closeActionModal();
        }
    },
});
const actionTitle = computed(() => {
    if (selectedAction.value === 'correct') {
        return t('pages.time_tracking.admin_operations.dialog.correct');
    }

    return t('pages.time_tracking.admin_operations.dialog.reject');
});
const historyRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.history'));
const overrideRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.closed_period_overrides'));
const auditRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.audit'));
const overviewFields = computed(() =>
    detailFields(['user_name', 'user_email', 'team_name', 'source_type', 'request_type', 'status'], values.value, t, locale.value),
);
const timingFields = computed(() => detailFields(['requested_at', 'decided_at', 'cancelled_at'], values.value, t, locale.value));
const noteFields = computed(() =>
    detailFields(['description', 'decision_reason', 'cancellation_reason'], values.value, t, locale.value).filter(
        (field) => field.formattedValue !== '-',
    ),
);
const technicalFields = computed(() =>
    detailFields(
        ['public_id', 'work_session_public_id', 'source_id', 'user_public_id', 'team_public_id', 'created_at', 'updated_at'],
        values.value,
        t,
        locale.value,
    ),
);
const comparisonRows = computed(() => [
    {
        key: 'original',
        label: t('pages.time_tracking.admin_detail.comparison.original'),
        started: detailValue('original_started_at', proposal.value.original_started_at, t, locale.value),
        ended: detailValue('original_ended_at', proposal.value.original_ended_at, t, locale.value),
        duration: detailValue('original_exact_seconds', proposal.value.original_exact_seconds, t, locale.value),
    },
    {
        key: 'proposed',
        label: t('pages.time_tracking.admin_detail.comparison.proposed'),
        started: detailValue('proposed_started_at', proposal.value.proposed_started_at, t, locale.value),
        ended: detailValue('proposed_ended_at', proposal.value.proposed_ended_at, t, locale.value),
        duration: detailValue('proposed_exact_seconds', proposal.value.proposed_exact_seconds, t, locale.value),
    },
    {
        key: 'final',
        label: t('pages.time_tracking.admin_detail.comparison.final'),
        started: detailValue('final_started_at', proposal.value.final_started_at, t, locale.value),
        ended: detailValue('final_ended_at', proposal.value.final_ended_at, t, locale.value),
        duration: detailValue('final_exact_seconds', proposal.value.final_exact_seconds, t, locale.value),
    },
]);

function canUseAdminRoute(routeName: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(routeName);
}

function canUseRoute(adminRouteName: string, managerRouteName: string): boolean {
    return isManagerSurface.value
        ? page.props.auth.availableApplicationRoutes.includes(managerRouteName)
        : canUseAdminRoute(adminRouteName);
}

function openAction(action: 'reject' | 'correct'): void {
    selectedAction.value = action;
    form.defaults({
        decision: action,
        reason: '',
        final_started_at: action === 'correct' ? (proposal.value.proposed_started_at ?? '') : '',
        final_ended_at: action === 'correct' ? (proposal.value.proposed_ended_at ?? '') : '',
    });
    form.reset();
    form.clearErrors();
}

function closeActionModal(): void {
    selectedAction.value = '';
    form.reset();
    form.clearErrors();
}

function submitAction(): void {
    if (values.value.public_id === '' || selectedAction.value === '') {
        return;
    }

    const endpoint = isManagerSurface.value
        ? `/manager/work-time/corrections/${values.value.public_id}/decide`
        : `/admin/work-time/corrections/${values.value.public_id}/decide`;

    form.post(endpoint, {
        preserveScroll: true,
        onSuccess: closeActionModal,
    });
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout
        :title="pageTitle"
        :title-icon="adminDetailIcon('correction')"
        :mode="surface"
        :subnavigation="isManagerSurface ? [] : adminDetailSubnavigation('corrections', t)"
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
                :icon="IconTool"
                tone="amber"
                v-if="
                    canUseRoute('admin.work-time.corrections.decide', 'manager.work-time.corrections.decide') &&
                    (availableActions.includes('reject') || availableActions.includes('correct'))
                "
            >
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="availableActions.includes('reject')"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                        @click="openAction('reject')"
                    >
                        <IconCircleX aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('pages.time_tracking.admin_operations.actions.reject') }}
                    </button>
                    <button
                        v-if="availableActions.includes('correct')"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-200 px-3 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950"
                        @click="openAction('correct')"
                    >
                        <IconFilePencil aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('pages.time_tracking.admin_operations.actions.correct') }}
                    </button>
                </div>
            </SurfaceCard>

            <SurfaceCard :title="pageTitle" :subtitle="t('navigation.work_time_corrections')" :icon="IconFilePencil" tone="teal">
                <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300"
                            >
                                <IconFilePencil aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            </div>
                            <div>
                                <div class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ values.user_name || pageTitle }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ values.team_name }}</div>
                            </div>
                            <StatusBadge
                                v-if="values.status"
                                :value="values.status"
                                :label="detailValue('status', values.status, t, locale)"
                            />
                        </div>

                        <dl class="grid gap-3 md:grid-cols-2">
                            <div v-for="field in overviewFields" :key="field.key" class="min-w-0">
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

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.proposals')" :icon="IconCalendarTime" tone="teal">
                <div class="grid gap-3 lg:grid-cols-3">
                    <div v-for="row in comparisonRows" :key="row.key" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ row.label }}</div>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between gap-3 text-sm">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ detailFieldLabel('started_at', t) }}</dt>
                                <dd class="text-right font-medium text-zinc-950 dark:text-zinc-50">{{ row.started }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 text-sm">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ detailFieldLabel('ended_at', t) }}</dt>
                                <dd class="text-right font-medium text-zinc-950 dark:text-zinc-50">{{ row.ended }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 text-sm">
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ detailFieldLabel('exact_seconds', t) }}</dt>
                                <dd class="text-right font-medium text-zinc-950 dark:text-zinc-50">{{ row.duration }}</dd>
                            </div>
                        </dl>
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

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.history')" :icon="IconHistory" tone="zinc">
                <div v-if="historyRows.length > 0" class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <div
                        v-for="(row, index) in historyRows"
                        :key="row.public_id || index"
                        class="grid gap-2 py-3 md:grid-cols-[12rem_1fr_18rem]"
                    >
                        <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                            {{ detailValue('action', row.action, t, locale) }}
                        </div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                            <div>{{ row.reason || '-' }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ row.actor_name || '-' }} · {{ row.actor_email || '-' }}
                            </div>
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 md:text-right">
                            {{ detailValue('occurred_at', row.occurred_at, t, locale) }}
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('pages.time_tracking.admin_detail.empty_section') }}</div>
            </SurfaceCard>

            <SurfaceCard
                :title="t('pages.time_tracking.admin_detail.sections.closed_period_overrides')"
                :icon="IconShieldCheck"
                tone="amber"
                v-if="overrideRows.length > 0"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="(row, index) in overrideRows"
                        :key="row.public_id || index"
                        class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                    >
                        <dl class="space-y-2">
                            <div
                                v-for="field in [
                                    'actor_scope',
                                    'admin_mode_confirmed',
                                    'high_risk_reauthenticated',
                                    'mfa_confirmed',
                                    'before_after_preview_confirmed',
                                    'authorized_at',
                                ]"
                                :key="field"
                                class="flex justify-between gap-3 text-sm"
                            >
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ detailFieldLabel(field, t) }}</dt>
                                <dd class="text-right font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ detailValue(field, row[field], t, locale) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
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

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.technical')" :icon="IconUser" tone="zinc">
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
            :icon="IconTool"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeActionModal"
        >
            <AtlasForm :processing="form.processing" @submit="submitAction">
                <div class="grid gap-4">
                    <FormTextarea
                        v-model="form.reason"
                        :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                        :error="form.errors.reason"
                    />
                    <div v-if="selectedAction === 'correct'" class="grid gap-3">
                        <FormDateTimeInput
                            v-model="form.final_started_at"
                            :label="t('pages.time_tracking.admin_operations.manual.started_at')"
                            :error="form.errors.final_started_at"
                        />
                        <FormDateTimeInput
                            v-model="form.final_ended_at"
                            :label="t('pages.time_tracking.admin_operations.manual.ended_at')"
                            :error="form.errors.final_ended_at"
                        />
                    </div>
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.time_tracking.admin_operations.dialog.submit')"
                    :submit-icon="IconTool"
                    submit-tone="danger"
                    :loading="form.processing"
                    @cancel="closeActionModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
