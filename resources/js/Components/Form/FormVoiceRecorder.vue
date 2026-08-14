<script setup lang="ts">
import { IconMicrophone, IconPlayerStop, IconRefresh, IconSend, IconTrash } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import FormButton from './FormButton.vue';

const props = withDefaults(defineProps<{ maxSeconds?: number; disabled?: boolean }>(), { maxSeconds: 900, disabled: false });
const emit = defineEmits<{ send: [blob: Blob, durationSeconds: number] }>();
const { t } = useTranslator();
const state = ref<'idle' | 'recording' | 'preview' | 'error'>('idle');
const seconds = ref(0);
const error = ref('');
const previewUrl = ref('');
let recorder: MediaRecorder | null = null;
let stream: MediaStream | null = null;
let timer: number | null = null;
let chunks: Blob[] = [];

const elapsed = computed(() => `${String(Math.floor(seconds.value / 60)).padStart(2, '0')}:${String(seconds.value % 60).padStart(2, '0')}`);

async function start(): Promise<void> {
    error.value = '';
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        seconds.value = 0;
        recorder = new MediaRecorder(stream);
        recorder.addEventListener('dataavailable', (event) => event.data.size > 0 && chunks.push(event.data));
        recorder.addEventListener('stop', finish);
        recorder.start();
        state.value = 'recording';
        timer = window.setInterval(() => {
            seconds.value += 1;
            if (seconds.value >= props.maxSeconds) stop();
        }, 1000);
    } catch {
        state.value = 'error';
        error.value = t('chat.voice.microphone_error');
    }
}

function stop(): void {
    if (recorder?.state === 'recording') recorder.stop();
    clearTimer();
}

function finish(): void {
    const mimeType = recorder?.mimeType || 'audio/webm';
    const blob = new Blob(chunks, { type: mimeType });
    releaseStream();
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = URL.createObjectURL(blob);
    state.value = 'preview';
}

function discard(): void {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
    chunks = [];
    seconds.value = 0;
    state.value = 'idle';
}

function send(): void {
    if (chunks.length === 0 || seconds.value < 1) return;
    emit('send', new Blob(chunks, { type: recorder?.mimeType || 'audio/webm' }), seconds.value);
    discard();
}

async function recordAgain(): Promise<void> {
    discard();
    await start();
}

function clearTimer(): void {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
}

function releaseStream(): void {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}

onBeforeUnmount(() => {
    clearTimer();
    releaseStream();
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});
</script>

<template>
    <section class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800" :aria-label="t('chat.voice.title')">
        <div v-if="state === 'idle' || state === 'error'" class="flex flex-wrap items-center gap-3">
            <FormButton :icon="IconMicrophone" :disabled="disabled" @click="start">{{ t('chat.voice.start') }}</FormButton>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ t('chat.voice.limit') }}</p>
        </div>
        <div v-else-if="state === 'recording'" class="flex flex-wrap items-center gap-3" aria-live="polite">
            <span class="h-3 w-3 animate-pulse rounded-full bg-rose-600" aria-hidden="true" />
            <span class="font-mono text-sm">{{ elapsed }}</span>
            <FormButton tone="neutral" :icon="IconPlayerStop" @click="stop">{{ t('chat.voice.stop') }}</FormButton>
        </div>
        <div v-else class="space-y-3">
            <audio :src="previewUrl" controls class="w-full" :aria-label="t('chat.voice.preview')" />
            <div class="flex flex-wrap gap-2">
                <FormButton :icon="IconSend" @click="send">{{ t('chat.voice.send') }}</FormButton>
                <FormButton tone="neutral" :icon="IconRefresh" @click="recordAgain">
                    {{ t('chat.voice.record_again') }}
                </FormButton>
                <FormButton tone="danger" :icon="IconTrash" @click="discard">{{ t('chat.voice.discard') }}</FormButton>
            </div>
        </div>
        <p v-if="error" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300">{{ error }}</p>
    </section>
</template>
