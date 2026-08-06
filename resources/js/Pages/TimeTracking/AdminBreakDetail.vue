<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconCalendarTime,
    IconCircleOff,
    IconDatabase,
    IconPlayerPause,
    IconShieldCheck,
    IconTool,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../Components/Form/DialogFormActions.vue';
import FormInput from '../../Components/Form/FormInput.vue';
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
    reason: '',
    converted_seconds: '',
});
const selectedAction = ref<'convert_excess_break' | 'force_close_break' | ''>('');
const pageTitle = computed(() => t(props.title));
const values = computed(() => recordMap(props.record));
const availableActions = computed(() => (values.value.available_actions ?? '').split(',').filter((action) => action !== ''));
const actionModalOpen = computed({
    get: () => selectedAction.value !== '',
    set: (open: boolean) => {
        if (!open) {
            closeActionModal();
        }
    },
});
const actionTitle = computed(() =>
    selectedAction.value === 'convert_excess_break'
        ? t('pages.time_tracking.admin_operations.dialog.convert_excess_break')
        : t('pages.time_tracking.admin_operations.dialog.force_close_break'),
);
const overviewFields = computed(() =>
    detailFields(['user_name', 'user_email', 'team_name', 'closure_reason'], values.value, t, locale.value),
);
const timingFields = computed(() => detailFields(['started_at', 'ended_at', 'exact_seconds'], values.value, t, locale.value));
const technicalFields = computed(() =>
    detailFields(
        ['public_id', 'work_session_public_id', 'user_public_id', 'team_public_id', 'created_at', 'updated_at'],
        values.value,
        t,
        locale.value,
    ),
);
const auditRows = computed(() => sectionRows(props.sections, 'pages.time_tracking.admin_detail.sections.audit'));

function canUseAdminRoute(routeName: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(routeName);
}

function canUseRoute(adminRouteName: string, managerRouteName: string): boolean {
    return isManagerSurface.value
        ? page.props.auth.availableApplicationRoutes.includes(managerRouteName)
        : canUseAdminRoute(adminRouteName);
}

function openAction(action: 'convert_excess_break' | 'force_close_break'): void {
    selectedAction.value = action;
    form.defaults({
        reason: '',
        converted_seconds: action === 'convert_excess_break' ? (values.value.excess_break_seconds ?? '') : '',
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

    const endpoint =
        selectedAction.value === 'convert_excess_break'
            ? `${isManagerSurface.value ? '/manager' : '/admin'}/work-time/breaks/${values.value.public_id}/convert-excess`
            : `${isManagerSurface.value ? '/manager' : '/admin'}/work-time/breaks/${values.value.public_id}/force-close`;

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
        :title-icon="adminDetailIcon('break')"
        :mode="surface"
        :subnavigation="isManagerSurface ? [] : adminDetailSubnavigation('breaks', t)"
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
                    (availableActions.includes('force_close_break') &&
                        canUseRoute('admin.work-time.breaks.force-close', 'manager.work-time.breaks.force-close')) ||
                    (availableActions.includes('convert_excess_break') &&
                        canUseRoute('admin.work-time.breaks.convert-excess', 'manager.work-time.breaks.convert-excess'))
                "
            >
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="
                            availableActions.includes('force_close_break') &&
                            canUseRoute('admin.work-time.breaks.force-close', 'manager.work-time.breaks.force-close')
                        "
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                        @click="openAction('force_close_break')"
                    >
                        <IconCircleOff aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('pages.time_tracking.admin_operations.actions.force_close') }}
                    </button>
                    <button
                        v-if="
                            availableActions.includes('convert_excess_break') &&
                            canUseRoute('admin.work-time.breaks.convert-excess', 'manager.work-time.breaks.convert-excess')
                        "
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-200 px-3 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950"
                        @click="openAction('convert_excess_break')"
                    >
                        <IconTool aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        {{ t('pages.time_tracking.admin_operations.actions.convert_excess') }}
                    </button>
                </div>
            </SurfaceCard>

            <SurfaceCard :title="pageTitle" :subtitle="t('navigation.work_time_breaks')" :icon="IconPlayerPause" tone="teal">
                <div class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300"
                            >
                                <IconPlayerPause aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            </div>
                            <div>
                                <div class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                                    {{ values.user_name || pageTitle }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ values.team_name }}</div>
                            </div>
                            <StatusBadge
                                :value="values.requires_manager_review === 'true'"
                                :true-label="t('pages.time_tracking.admin_operations.filters.requires_review')"
                                :false-label="t('datatable.boolean.no')"
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
            :icon="IconTool"
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
                <FormInput
                    v-if="selectedAction === 'convert_excess_break'"
                    v-model="form.converted_seconds"
                    type="number"
                    min="1"
                    :label="t('pages.time_tracking.admin_operations.dialog.converted_seconds')"
                    :error="form.errors.converted_seconds"
                />
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
