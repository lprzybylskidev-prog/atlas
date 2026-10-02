<script setup lang="ts">
import { IconVideoOff } from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import {
    availableMediaDevices,
    callPreferences,
    saveCallPreferences,
    type CallPreferences,
    type MediaDeviceOptions,
} from '../../Services/chatCalls';
import FormCheckbox from '../Form/FormCheckbox.vue';
import FormSelect, { type FormSelectOption } from '../Form/FormSelect.vue';

export interface MediaDevicePreparation {
    cameraEnabled: boolean;
    microphoneEnabled: boolean;
    preferences: CallPreferences;
    devices: MediaDeviceOptions;
}

withDefaults(
    defineProps<{
        showOutgoingCameraPreference?: boolean;
        testId?: string;
    }>(),
    {
        showOutgoingCameraPreference: false,
        testId: 'media-device-setup',
    },
);

const emit = defineEmits<{
    change: [preparation: MediaDevicePreparation];
    'update:busy': [busy: boolean];
}>();

const { t } = useTranslator();
const preview = ref<HTMLVideoElement | null>(null);
const previewStream = ref<MediaStream | null>(null);
const cameraEnabled = ref(false);
const microphoneEnabled = ref(true);
const error = ref<string | null>(null);
const preferences = ref<CallPreferences>(emptyPreferences());
const devices = ref<MediaDeviceOptions>(emptyDevices());
let streamRequest = 0;

const cameraOptions = computed(() => deviceOptions(devices.value.cameras, t('calls.devices.default_camera')));
const microphoneOptions = computed(() => deviceOptions(devices.value.microphones, t('calls.devices.default_microphone')));
const speakerOptions = computed(() => deviceOptions(devices.value.speakers, t('calls.devices.default_speaker')));
const cameraDeviceId = computed({
    get: () => preferences.value.cameraDeviceId ?? '',
    set: (value: string | number) => (preferences.value.cameraDeviceId = nullableDeviceId(value)),
});
const microphoneDeviceId = computed({
    get: () => preferences.value.microphoneDeviceId ?? '',
    set: (value: string | number) => (preferences.value.microphoneDeviceId = nullableDeviceId(value)),
});
const speakerDeviceId = computed({
    get: () => preferences.value.speakerDeviceId ?? '',
    set: (value: string | number) => (preferences.value.speakerDeviceId = nullableDeviceId(value)),
});

async function prepare(requestedCamera: boolean | null = null): Promise<MediaDevicePreparation> {
    emit('update:busy', true);
    error.value = null;
    stop();

    try {
        preferences.value = await callPreferences();
        cameraEnabled.value = requestedCamera ?? preferences.value.outgoingCameraEnabled;
        microphoneEnabled.value = true;
        await refreshPreview();
        devices.value = await availableMediaDevices();
    } catch {
        error.value = t('calls.errors.media_permission');
        devices.value = await availableMediaDevices().catch(emptyDevices);
    } finally {
        emit('update:busy', false);
    }

    return publish();
}

async function switchDevice(kind: MediaDeviceKind, deviceId: string | number | null): Promise<void> {
    if (deviceId === null) return;

    if (kind === 'videoinput' || kind === 'audioinput') {
        await refreshPreview().catch(() => {
            error.value = t('calls.errors.media_permission');
        });
    }

    await persistPreferences();
}

async function updateCamera(value: boolean | string[]): Promise<void> {
    if (typeof value !== 'boolean') return;

    cameraEnabled.value = value;
    await refreshPreview().catch(() => {
        error.value = t('calls.errors.media_permission');
    });
    publish();
}

function updateMicrophone(value: boolean | string[]): void {
    if (typeof value !== 'boolean') return;
    microphoneEnabled.value = value;
    publish();
}

async function persistPreferences(): Promise<void> {
    preferences.value = await saveCallPreferences(preferences.value);
    publish();
}

async function refreshPreview(): Promise<void> {
    stop();
    const request = streamRequest;
    const stream = await preferredMediaStream().catch(async () => {
        preferences.value.cameraDeviceId = null;
        preferences.value.microphoneDeviceId = null;

        return navigator.mediaDevices.getUserMedia({ audio: true, video: cameraEnabled.value });
    });
    if (request !== streamRequest) {
        stream.getTracks().forEach((track) => track.stop());
        return;
    }
    previewStream.value = stream;
    await nextTick();
    if (preview.value) preview.value.srcObject = previewStream.value;
    error.value = null;
}

function preferredMediaStream(): Promise<MediaStream> {
    return navigator.mediaDevices.getUserMedia({
        audio: preferences.value.microphoneDeviceId ? { deviceId: { exact: preferences.value.microphoneDeviceId } } : true,
        video: cameraEnabled.value
            ? preferences.value.cameraDeviceId
                ? { deviceId: { exact: preferences.value.cameraDeviceId } }
                : true
            : false,
    });
}

function publish(): MediaDevicePreparation {
    const preparation = {
        cameraEnabled: cameraEnabled.value,
        microphoneEnabled: microphoneEnabled.value,
        preferences: { ...preferences.value },
        devices: {
            cameras: [...devices.value.cameras],
            microphones: [...devices.value.microphones],
            speakers: [...devices.value.speakers],
        },
    };
    emit('change', preparation);

    return preparation;
}

function stop(): void {
    streamRequest += 1;
    previewStream.value?.getTracks().forEach((track) => track.stop());
    previewStream.value = null;
    if (preview.value) preview.value.srcObject = null;
}

function deviceOptions(items: MediaDeviceInfo[], fallback: string): FormSelectOption[] {
    return [
        { value: '', label: fallback },
        ...items.map((device, index) => ({ value: device.deviceId, label: device.label || `${fallback} ${index + 1}` })),
    ];
}

function nullableDeviceId(value: string | number): string | null {
    const deviceId = String(value);
    return deviceId === '' ? null : deviceId;
}

function emptyPreferences(): CallPreferences {
    return { cameraDeviceId: null, microphoneDeviceId: null, speakerDeviceId: null, outgoingCameraEnabled: false };
}

function emptyDevices(): MediaDeviceOptions {
    return { cameras: [], microphones: [], speakers: [] };
}

onBeforeUnmount(stop);

defineExpose({ prepare, stop });
</script>

<template>
    <div class="space-y-4" :data-testid="testId">
        <video
            v-if="cameraEnabled"
            ref="preview"
            autoplay
            muted
            playsinline
            class="aspect-video w-full rounded-lg bg-zinc-950 object-cover"
        />
        <div v-else class="flex aspect-video items-center justify-center rounded-lg bg-zinc-900 text-zinc-300">
            <IconVideoOff aria-hidden="true" class="h-10 w-10" :stroke-width="1.5" />
        </div>
        <p v-if="error" class="rounded-lg bg-rose-50 p-3 text-rose-800 dark:bg-rose-950 dark:text-rose-200" role="alert">{{ error }}</p>
        <div class="grid gap-3 md:grid-cols-3">
            <FormSelect
                v-model="cameraDeviceId"
                :label="t('calls.devices.camera')"
                :options="cameraOptions"
                @update:model-value="switchDevice('videoinput', $event)"
            />
            <FormSelect
                v-model="microphoneDeviceId"
                :label="t('calls.devices.microphone')"
                :options="microphoneOptions"
                @update:model-value="switchDevice('audioinput', $event)"
            />
            <FormSelect
                v-model="speakerDeviceId"
                :label="t('calls.devices.speaker')"
                :options="speakerOptions"
                @update:model-value="switchDevice('audiooutput', $event)"
            />
        </div>
        <div class="flex flex-wrap gap-4">
            <FormCheckbox :model-value="cameraEnabled" :label="t('calls.pre_call.camera')" @update:model-value="updateCamera" />
            <FormCheckbox :model-value="microphoneEnabled" :label="t('calls.pre_call.microphone')" @update:model-value="updateMicrophone" />
            <FormCheckbox
                v-if="showOutgoingCameraPreference"
                v-model="preferences.outgoingCameraEnabled"
                :label="t('calls.pre_call.remember_camera')"
                @update:model-value="persistPreferences"
            />
        </div>
    </div>
</template>
