<script setup lang="ts">
import { IconDeviceDesktopShare, IconMicrophone, IconMicrophoneOff, IconVideo, IconVideoOff } from '@tabler/icons-vue';

import FormButton from '../Form/FormButton.vue';
import { useTranslator } from '../../Localization/translator';

defineProps<{
    title: string;
    microphoneEnabled: boolean;
    cameraEnabled: boolean;
    screenSharing: boolean;
    recordingStatus: string;
    organizer: boolean;
}>();

const emit = defineEmits<{
    microphone: [];
    camera: [];
    restore: [];
    leave: [];
    end: [];
}>();

const { t } = useTranslator();
</script>

<template>
    <aside
        class="fixed right-4 bottom-4 z-70 w-[min(42rem,calc(100vw-2rem))] rounded-lg border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-800 dark:bg-zinc-950"
        role="region"
        :aria-label="t('meetings.rtc.minimized_label')"
        data-testid="minimized-meeting-session"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <strong class="truncate text-sm text-zinc-950 dark:text-zinc-50">{{ title }}</strong>
            <span
                v-if="['recording', 'paused'].includes(recordingStatus)"
                class="rounded-full px-2 py-1 text-xs font-semibold"
                :class="
                    recordingStatus === 'paused'
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                        : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
                "
            >
                {{ recordingStatus === 'paused' ? t('meetings.recording.indicator.paused') : t('meetings.recording.indicator.active') }}
            </span>
        </div>
        <div class="flex flex-wrap gap-2">
            <FormButton tone="neutral" :icon="microphoneEnabled ? IconMicrophone : IconMicrophoneOff" @click="emit('microphone')">
                {{ t('meetings.rtc.microphone') }}
            </FormButton>
            <FormButton tone="neutral" :icon="cameraEnabled ? IconVideo : IconVideoOff" @click="emit('camera')">
                {{ t('meetings.rtc.camera') }}
            </FormButton>
            <span
                v-if="screenSharing"
                class="inline-flex min-h-10 items-center gap-2 rounded-md bg-sky-50 px-3 text-sm text-sky-800 dark:bg-sky-950 dark:text-sky-200"
            >
                <IconDeviceDesktopShare aria-hidden="true" class="h-4 w-4" /><span>{{ t('meetings.rtc.sharing') }}</span>
            </span>
            <FormButton :icon="IconVideo" @click="emit('restore')">{{ t('meetings.rtc.rejoin') }}</FormButton>
            <FormButton tone="danger" @click="emit('leave')">{{ t('meetings.rtc.leave') }}</FormButton>
            <FormButton v-if="organizer" tone="danger" @click="emit('end')">{{ t('meetings.rtc.end') }}</FormButton>
        </div>
    </aside>
</template>
