<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconBriefcase, IconClockHour4, IconLogin2, IconShieldLock } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

interface OtherWorkSession {
    publicId: string;
    startedAt: string;
    elapsedSeconds: number;
    categoryLabel: string;
    description: string;
    approvalStatus: string;
}

const props = defineProps<{
    otherWorkSession: OtherWorkSession;
    mfaRequired: boolean;
}>();

const { t } = useTranslator();
const tick = ref(0);
const form = useForm({
    end_note: '',
    password: '',
    mfa_code: '',
});
let timer: number | undefined;

const elapsedSeconds = computed(() => props.otherWorkSession.elapsedSeconds + tick.value);
const approvalLabels = computed<Record<string, string>>(() => ({
    approved: t('pages.time_tracking.other_work_lock.approval_status.approved'),
    pending: t('pages.time_tracking.other_work_lock.approval_status.pending'),
    under_review: t('pages.time_tracking.other_work_lock.approval_status.under_review'),
}));
const approvalLabel = computed(() => approvalLabels.value[props.otherWorkSession.approvalStatus] ?? props.otherWorkSession.approvalStatus);

onMounted(() => {
    timer = window.setInterval(() => {
        tick.value += 1;
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer !== undefined) {
        window.clearInterval(timer);
    }
});

function submit(): void {
    form.post('/user/work-time/other-work/end', {
        preserveScroll: true,
        onSuccess: () => form.reset('end_note', 'password', 'mfa_code'),
    });
}

function formatDuration(seconds: number): string {
    const safeSeconds = Math.max(0, seconds);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const secondsPart = safeSeconds % 60;

    return t('pages.time_tracking.other_work_lock.duration', { hours, minutes, seconds: secondsPart });
}
</script>

<template>
    <Head :title="t('pages.time_tracking.other_work_lock.head_title')" />
    <AuthLayout
        :title="t('pages.time_tracking.other_work_lock.notice_title')"
        :subtitle="t('pages.time_tracking.other_work_lock.notice_body')"
        :frame-content="false"
        content-size="4xl"
    >
        <PageStack>
            <div class="grid gap-3">
                <OperationalMetricTile
                    :label="t('pages.time_tracking.other_work_lock.metrics.elapsed')"
                    :value="formatDuration(elapsedSeconds)"
                    :icon="IconClockHour4"
                    tone="sky"
                />
            </div>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)]">
                <SurfaceCard
                    :title="otherWorkSession.categoryLabel"
                    :subtitle="t('pages.time_tracking.other_work_lock.category')"
                    :icon="IconBriefcase"
                    tone="sky"
                >
                    <template #actions>
                        <StatusBadge :label="approvalLabel" tone="warning" />
                    </template>
                    <div class="mt-5 rounded-md bg-zinc-50 p-4 text-sm leading-6 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                        {{ otherWorkSession.description }}
                    </div>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.time_tracking.other_work_lock.return_form_title')" :icon="IconLogin2" tone="sky">
                    <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                        <FormTextarea
                            id="other-work-end-note"
                            v-model="form.end_note"
                            :label="t('pages.time_tracking.other_work_lock.end_note')"
                            :error="form.errors.end_note"
                            :rows="5"
                        />
                        <FormInput
                            id="other-work-return-password"
                            v-model="form.password"
                            :label="t('pages.time_tracking.other_work_lock.password')"
                            type="password"
                            autocomplete="current-password"
                            :leading-icon="IconShieldLock"
                            :error="form.errors.password"
                        />
                        <FormInput
                            v-if="mfaRequired"
                            id="other-work-return-mfa"
                            v-model="form.mfa_code"
                            :label="t('pages.time_tracking.other_work_lock.mfa_code')"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :leading-icon="IconShieldLock"
                            :error="form.errors.mfa_code"
                        />
                        <FormButton type="submit" class="w-full sm:w-auto" :loading="form.processing" :icon="IconLogin2">
                            {{
                                form.processing
                                    ? t('pages.time_tracking.other_work_lock.submitting')
                                    : t('pages.time_tracking.other_work_lock.submit')
                            }}
                        </FormButton>
                    </AtlasForm>
                </SurfaceCard>
            </section>
        </PageStack>
    </AuthLayout>
</template>
