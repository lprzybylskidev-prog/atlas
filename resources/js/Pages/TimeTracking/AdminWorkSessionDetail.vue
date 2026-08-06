<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconBriefcase,
    IconClockHour4,
    IconCircleOff,
    IconDatabase,
    IconFilePencil,
    IconPlayerPause,
    IconShieldCheck,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

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
import { formatStatus } from '../../Utils/formatters';

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
const form = useForm({ reason: '' });
const pageTitle = computed(() => t(props.title));
const values = computed(() => recordMap(props.record));
const availableActions = computed(() => (values.value.available_actions ?? '').split(',').filter((action) => action !== ''));
const actionModalOpen = computed({
    get: () => form.processing || selectedAction.value !== '',
    set: (open: boolean) => {
        if (!open) {
            closeActionModal();
        }
    },
});
const selectedAction = ref('');
const overviewFields = computed(() =>
    detailFields(['user_name', 'user_email', 'team_name', 'closure_reason'], values.value, t, locale.value),
);
const timingFields = computed(() => detailFields(['started_at', 'ended_at', 'exact_seconds'], values.value, t, locale.value));
const technicalFields = computed(() =>
    detailFields(
        ['public_id', 'laravel_session_id', 'user_public_id', 'team_public_id', 'created_at', 'updated_at'],
        values.value,
        t,
        locale.value,
    ),
);
const moduleRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.module_segments'));
const breakRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.breaks'));
const otherWorkRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.other_work'));
const maintenanceRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.maintenance'));
const correctionRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.corrections'));
const auditRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.audit'));

function canUseAdminRoute(routeName: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(routeName);
}

function canUseRoute(adminRouteName: string, managerRouteName: string): boolean {
    return isManagerSurface.value
        ? page.props.auth.availableApplicationRoutes.includes(managerRouteName)
        : canUseAdminRoute(adminRouteName);
}

function openTerminateAction(): void {
    selectedAction.value = 'terminate';
    form.defaults({ reason: '' });
    form.reset();
    form.clearErrors();
}

function closeActionModal(): void {
    selectedAction.value = '';
    form.reset();
    form.clearErrors();
}

function submitAction(): void {
    if (values.value.public_id === '') {
        return;
    }

    const endpoint = isManagerSurface.value
        ? `/manager/work-time/work-sessions/${values.value.public_id}/terminate`
        : `/admin/work-time/work-sessions/${values.value.public_id}/terminate`;

    form.post(endpoint, {
        preserveScroll: true,
        onSuccess: closeActionModal,
    });
}

function timeRange(row: Record<string, string>, endKey = 'ended_at'): string {
    const startedAt = detailValue('started_at', row.started_at, t, locale.value);
    const endedAt =
        row[endKey] === undefined || row[endKey] === ''
            ? t('pages.time_tracking.admin_detail.timeline.ongoing')
            : detailValue(endKey, row[endKey], t, locale.value);

    return `${startedAt} - ${endedAt}`;
}

function durationText(row: Record<string, string>): string {
    if (row.exact_seconds !== undefined && row.exact_seconds !== '') {
        return detailValue('exact_seconds', row.exact_seconds, t, locale.value);
    }

    return row.ended_at === undefined || row.ended_at === ''
        ? t('pages.time_tracking.admin_detail.timeline.ongoing')
        : t('pages.time_tracking.admin_detail.timeline.no_duration');
}

function otherWorkTitle(row: Record<string, string>): string {
    if (row.category_label !== undefined && row.category_label !== '') {
        return row.category_label;
    }

    if (row.category_key !== undefined && row.category_key !== '') {
        return formatStatus(row.category_key);
    }

    return t('pages.time_tracking.admin_detail.timeline.uncategorized_other_work');
}

function maintenanceEnd(row: Record<string, string>): string {
    return row.completed_at !== undefined && row.completed_at !== ''
        ? detailValue('completed_at', row.completed_at, t, locale.value)
        : t('pages.time_tracking.admin_detail.timeline.ongoing');
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout
        :title="pageTitle"
        :title-icon="adminDetailIcon('work_session')"
        :mode="surface"
        :subnavigation="isManagerSurface ? [] : adminDetailSubnavigation('work_sessions', t)"
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
                :icon="IconFilePencil"
                tone="amber"
                v-if="
                    availableActions.includes('terminate') &&
                    canUseRoute('admin.work-time.work-sessions.terminate', 'manager.work-time.work-sessions.terminate')
                "
            >
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                    @click="openTerminateAction"
                >
                    <IconCircleOff aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('pages.time_tracking.admin_operations.actions.terminate') }}
                </button>
            </SurfaceCard>

            <SurfaceCard :title="pageTitle" :subtitle="t('navigation.work_time_sessions')" :icon="IconClockHour4" tone="teal">
                <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300"
                            >
                                <IconClockHour4 aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            </div>
                            <div>
                                <div class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ values.user_name || pageTitle }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ values.team_name }}</div>
                            </div>
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
                            <div class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ field.label }}</div>
                            <div class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ field.formattedValue }}</div>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.module_segments')" :icon="IconDatabase" tone="zinc">
                <div v-if="moduleRows.length > 0" class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="(row, index) in moduleRows"
                        :key="row.public_id || index"
                        class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ detailValue('context_key', row.context_key, t, locale) }}
                                </div>
                                <div class="mt-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                    {{ detailValue('module_key', row.module_key, t, locale) }}
                                </div>
                            </div>
                            <StatusBadge
                                :value="row.ended_at === '' ? 'open' : 'closed'"
                                :label="
                                    row.ended_at === ''
                                        ? t('pages.time_tracking.user_report.status.open')
                                        : t('pages.time_tracking.user_report.status.closed')
                                "
                            />
                        </div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ detailFieldLabel('started_at', t) }}
                                </dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ detailValue('started_at', row.started_at, t, locale) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ detailFieldLabel('ended_at', t) }}
                                </dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{
                                        row.ended_at === ''
                                            ? t('pages.time_tracking.admin_detail.timeline.ongoing')
                                            : detailValue('ended_at', row.ended_at, t, locale)
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ detailFieldLabel('exact_seconds', t) }}
                                </dt>
                                <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                    {{ durationText(row) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                >
                    {{ t('pages.time_tracking.admin_detail.empty.module_segments') }}
                </div>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.breaks')" :icon="IconPlayerPause" tone="zinc">
                    <div v-if="breakRows.length > 0" class="space-y-3">
                        <div
                            v-for="(row, index) in breakRows"
                            :key="row.public_id || index"
                            class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ durationText(row) }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ timeRange(row) }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <StatusBadge
                                        :value="row.requires_manager_review === 'true' ? 'under_review' : 'closed'"
                                        :label="
                                            row.requires_manager_review === 'true'
                                                ? t('pages.time_tracking.user_report.status.under_review')
                                                : t('pages.time_tracking.user_report.status.closed')
                                        "
                                    />
                                    <StatusBadge
                                        v-if="row.closure_reason"
                                        :value="row.closure_reason"
                                        :label="detailValue('closure_reason', row.closure_reason, t, locale)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                    >
                        {{ t('pages.time_tracking.admin_detail.empty.breaks') }}
                    </div>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.other_work')" :icon="IconBriefcase" tone="zinc">
                    <div v-if="otherWorkRows.length > 0" class="space-y-3">
                        <div
                            v-for="(row, index) in otherWorkRows"
                            :key="row.public_id || index"
                            class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="break-words text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ otherWorkTitle(row) }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ timeRange(row) }}
                                    </div>
                                    <div
                                        v-if="row.description"
                                        class="mt-3 whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300"
                                    >
                                        {{ row.description }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <StatusBadge
                                        v-if="row.approval_status"
                                        :value="row.approval_status"
                                        :label="detailValue('approval_status', row.approval_status, t, locale)"
                                    />
                                    <StatusBadge
                                        v-if="row.requires_manager_review === 'true'"
                                        value="under_review"
                                        :label="t('pages.time_tracking.user_report.status.under_review')"
                                    />
                                </div>
                            </div>
                            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                        {{ detailFieldLabel('exact_seconds', t) }}
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                        {{ durationText(row) }}
                                    </dd>
                                </div>
                                <div v-if="row.end_note">
                                    <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                                        {{ detailFieldLabel('end_note', t) }}
                                    </dt>
                                    <dd class="mt-1 break-words text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                        {{ row.end_note }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                    >
                        {{ t('pages.time_tracking.admin_detail.empty.other_work') }}
                    </div>
                </SurfaceCard>
            </div>

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.corrections')" :icon="IconFilePencil" tone="zinc">
                <div v-if="correctionRows.length > 0" class="space-y-3">
                    <div
                        v-for="(row, index) in correctionRows"
                        :key="row.public_id || index"
                        class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ detailValue('request_type', row.request_type, t, locale) }}
                                </div>
                                <div
                                    v-if="row.description"
                                    class="mt-2 whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300"
                                >
                                    {{ row.description }}
                                </div>
                            </div>
                            <StatusBadge v-if="row.status" :value="row.status" :label="detailValue('status', row.status, t, locale)" />
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>
                                {{ detailFieldLabel('requested_at', t) }}:
                                {{ detailValue('requested_at', row.requested_at, t, locale) }}
                            </span>
                            <span v-if="row.decided_at">
                                {{ detailFieldLabel('decided_at', t) }}: {{ detailValue('decided_at', row.decided_at, t, locale) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                >
                    {{ t('pages.time_tracking.admin_detail.empty.corrections') }}
                </div>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.maintenance')" :icon="IconShieldCheck" tone="zinc">
                    <div v-if="maintenanceRows.length > 0" class="space-y-3">
                        <div
                            v-for="(row, index) in maintenanceRows"
                            :key="row.public_id || index"
                            class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ detailValue('kind', row.kind, t, locale) }}
                                    </div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ detailValue('started_at', row.started_at, t, locale) }} -
                                        {{ maintenanceEnd(row) }}
                                    </div>
                                </div>
                                <StatusBadge v-if="row.status" :value="row.status" :label="detailValue('status', row.status, t, locale)" />
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                    >
                        {{ t('pages.time_tracking.admin_detail.empty.maintenance') }}
                    </div>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.audit')" :icon="IconShieldCheck" tone="zinc">
                    <div v-if="auditRows.length > 0" class="space-y-3">
                        <div
                            v-for="(row, index) in auditRows"
                            :key="row.public_id || index"
                            class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="break-words text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                        {{ detailValue('action', row.action, t, locale) }}
                                    </div>
                                    <div
                                        v-if="row.reason"
                                        class="mt-2 whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300"
                                    >
                                        {{ row.reason }}
                                    </div>
                                </div>
                                <StatusBadge v-if="row.result" :value="row.result" :label="detailValue('result', row.result, t, locale)" />
                            </div>
                            <div class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ detailValue('occurred_at', row.occurred_at, t, locale) }}
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400"
                    >
                        {{ t('pages.time_tracking.admin_detail.empty.audit') }}
                    </div>
                </SurfaceCard>
            </div>

            <SurfaceCard :title="t('pages.time_tracking.admin_detail.sections.technical')" :icon="IconDatabase" tone="zinc">
                <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="field in technicalFields"
                        :key="field.key"
                        class="min-w-0 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                    >
                        <dt class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                            {{ detailFieldLabel(field.key, t) }}
                        </dt>
                        <dd class="mt-1 break-words text-sm text-zinc-950 dark:text-zinc-50">{{ field.formattedValue }}</dd>
                    </div>
                </dl>
            </SurfaceCard>
        </PageStack>

        <DialogPanel
            v-model:open="actionModalOpen"
            :title="t('pages.time_tracking.admin_operations.dialog.terminate')"
            :icon="IconFilePencil"
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
                    :submit-icon="IconFilePencil"
                    submit-tone="danger"
                    :loading="form.processing"
                    @cancel="closeActionModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
