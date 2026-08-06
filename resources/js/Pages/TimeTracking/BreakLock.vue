<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconClockHour4, IconLogin2, IconPlayerPause, IconShieldLock } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

interface BreakSession {
    publicId: string;
    startedAt: string;
    elapsedSeconds: number;
    maximumSeconds: number;
    warningBeforeMaximumSeconds: number;
    remainingSeconds: number;
    exceededSeconds: number;
}

const props = defineProps<{
    breakSession: BreakSession;
    mfaRequired: boolean;
}>();

const { t } = useTranslator();
const tick = ref(0);
const form = useForm({
    password: '',
    mfa_code: '',
});
let timer: number | undefined;

const elapsedSeconds = computed(() => props.breakSession.elapsedSeconds + tick.value);
const remainingSeconds = computed(() => Math.max(0, props.breakSession.maximumSeconds - elapsedSeconds.value));
const exceededSeconds = computed(() => Math.max(0, elapsedSeconds.value - props.breakSession.maximumSeconds));

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
    form.post('/user/work-time/break/end', {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'mfa_code'),
    });
}

function formatDuration(seconds: number): string {
    const safeSeconds = Math.max(0, seconds);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const secondsPart = safeSeconds % 60;

    return t('pages.time_tracking.break_lock.duration', { hours, minutes, seconds: secondsPart });
}
</script>

<template>
    <Head :title="t('pages.time_tracking.break_lock.head_title')" />
    <AuthLayout
        :title="t('pages.time_tracking.break_lock.notice_title')"
        :subtitle="t('pages.time_tracking.break_lock.notice_body')"
        :frame-content="false"
        content-size="4xl"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-3">
                <OperationalMetricTile
                    :label="t('pages.time_tracking.break_lock.metrics.elapsed')"
                    :value="formatDuration(elapsedSeconds)"
                    :icon="IconClockHour4"
                    tone="amber"
                />
                <OperationalMetricTile
                    v-if="exceededSeconds === 0"
                    :label="t('pages.time_tracking.break_lock.metrics.remaining')"
                    :value="formatDuration(remainingSeconds)"
                    :icon="IconPlayerPause"
                    tone="teal"
                />
                <OperationalMetricTile
                    v-else
                    :label="t('pages.time_tracking.break_lock.metrics.exceeded')"
                    :value="formatDuration(exceededSeconds)"
                    :icon="IconShieldLock"
                    tone="rose"
                />
            </div>

            <SurfaceCard class="max-w-xl" :title="t('pages.time_tracking.break_lock.return_form_title')" :icon="IconLogin2" tone="amber">
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                    <FormInput
                        id="break-return-password"
                        v-model="form.password"
                        :label="t('pages.time_tracking.break_lock.password')"
                        type="password"
                        autocomplete="current-password"
                        :leading-icon="IconShieldLock"
                        :error="form.errors.password"
                    />
                    <FormInput
                        v-if="mfaRequired"
                        id="break-return-mfa"
                        v-model="form.mfa_code"
                        :label="t('pages.time_tracking.break_lock.mfa_code')"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        :leading-icon="IconShieldLock"
                        :error="form.errors.mfa_code"
                    />
                    <FormButton type="submit" class="w-full sm:w-auto" :loading="form.processing" :icon="IconLogin2">
                        {{ form.processing ? t('pages.time_tracking.break_lock.submitting') : t('pages.time_tracking.break_lock.submit') }}
                    </FormButton>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AuthLayout>
</template>
