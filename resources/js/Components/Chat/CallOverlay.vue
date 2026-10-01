<script setup lang="ts">
import {
    IconDeviceDesktopShare,
    IconDeviceDesktopOff,
    IconMicrophone,
    IconMicrophoneOff,
    IconPhone,
    IconPhoneOff,
    IconVideo,
    IconVideoOff,
} from '@tabler/icons-vue';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import {
    availableMediaDevices,
    callPreferences,
    CallRealtimeClient,
    currentCall,
    declineCall,
    joinCall,
    leaveCall,
    saveCallPreferences,
    startCall,
    updateCallMedia,
    updateScreenShare,
    type CallJoinResponse,
    type CallPreferences,
    type CallSnapshot,
    type MediaDeviceOptions,
} from '../../Services/chatCalls';
import type { CallMediaSession } from '../../Services/callMediaSession';
import type { AtlasPageProps } from '../../Types/inertia';
import DialogPanel from '../DialogPanel.vue';
import FormButton from '../Form/FormButton.vue';
import FormCheckbox from '../Form/FormCheckbox.vue';
import FormSelect, { type FormSelectOption } from '../Form/FormSelect.vue';

const { t } = useTranslator();
const page = usePage<AtlasPageProps>();
const call = ref<CallSnapshot | null>(null);
const stage = ref<'idle' | 'precall' | 'connecting' | 'connected' | 'result'>('idle');
const startConversation = ref<string | null>(null);
const cameraEnabled = ref(false);
const microphoneEnabled = ref(true);
const screenSharing = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const resultMessage = ref<string | null>(null);
const preview = ref<HTMLVideoElement | null>(null);
const remoteMedia = ref<HTMLDivElement | null>(null);
const previewStream = ref<MediaStream | null>(null);
const session = ref<CallMediaSession | null>(null);
const preferences = ref<CallPreferences>({
    cameraDeviceId: null,
    microphoneDeviceId: null,
    speakerDeviceId: null,
    outgoingCameraEnabled: false,
});
const devices = ref<MediaDeviceOptions>({ cameras: [], microphones: [], speakers: [] });
let pollTimer: number | null = null;
let lastAlertedCall: string | null = null;
let realtime: CallRealtimeClient | null = null;

const dialogOpen = computed(() => stage.value !== 'idle' || (call.value !== null && !call.value.teamJoinStyle));
const teamBanner = computed(() => call.value?.teamJoinStyle === true && call.value.canRejoin && stage.value === 'idle');
const canPrepare = computed(
    () =>
        call.value !== null &&
        (call.value.incoming ||
            call.value.teamJoinStyle ||
            call.value.canRejoin ||
            (call.value.currentUserState === 'joined' && stage.value !== 'connected')),
);
const title = computed(() => {
    if (stage.value === 'connected') return call.value?.conversationLabel ?? t('calls.title');
    if (stage.value === 'result') return t('calls.title');
    if (stage.value === 'precall') return t('calls.pre_call.title');
    if (call.value?.incoming) return t('calls.incoming.title');
    if (call.value?.teamJoinStyle) return t('calls.team_available.title');
    return t('calls.rejoin.title');
});
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

onMounted(() => {
    window.addEventListener('atlas:call-prepare', handlePrepare);
    void refresh();
    pollTimer = window.setInterval(() => void refresh(), 8_000);
    const userPublicId = page.props.auth.user?.publicId;
    if (userPublicId) {
        realtime = new CallRealtimeClient(userPublicId, () => void refresh());
        realtime.start();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('atlas:call-prepare', handlePrepare);
    if (pollTimer !== null) window.clearInterval(pollTimer);
    stopPreview();
    session.value?.disconnect();
    realtime?.stop();
});

async function refresh(): Promise<void> {
    if (stage.value === 'connecting') return;

    try {
        const current = await currentCall();
        if (current === null && stage.value === 'connected') {
            session.value?.disconnect();
            session.value = null;
            stage.value = 'idle';
            screenSharing.value = false;
        }
        call.value = current;
        alertIncoming(call.value);
    } catch {
        // The global shell stays usable when Chat or RTC is unavailable.
    }
}

function handlePrepare(event: Event): void {
    if (!(event instanceof CustomEvent)) return;
    const detail = event.detail as unknown;
    if (!isPrepareCallDetail(detail)) return;
    startConversation.value = detail.conversationPublicId;
    void prepareDevices(detail.cameraEnabled);
}

async function prepareExisting(withCamera: boolean): Promise<void> {
    startConversation.value = null;
    await prepareDevices(withCamera);
}

async function prepareDevices(requestedCamera: boolean | null = cameraEnabled.value): Promise<void> {
    error.value = null;
    resultMessage.value = null;
    stage.value = 'precall';
    busy.value = true;

    try {
        preferences.value = await callPreferences();
        cameraEnabled.value = requestedCamera ?? preferences.value.outgoingCameraEnabled;
        microphoneEnabled.value = true;
        previewStream.value = await preferredMediaStream().catch(() =>
            navigator.mediaDevices.getUserMedia({ audio: true, video: cameraEnabled.value }),
        );
        devices.value = await availableMediaDevices();
        await nextTick();
        if (preview.value) preview.value.srcObject = previewStream.value;
    } catch {
        error.value = t('calls.errors.media_permission');
        devices.value = await availableMediaDevices().catch(() => ({ cameras: [], microphones: [], speakers: [] }));
    } finally {
        busy.value = false;
    }
}

async function connect(): Promise<void> {
    busy.value = true;
    error.value = null;
    stage.value = 'connecting';

    try {
        await persistPreferences();
        let response = startConversation.value
            ? await startCall(startConversation.value, cameraEnabled.value, crypto.randomUUID())
            : await joinCall(requiredCall().publicId, cameraEnabled.value, microphoneEnabled.value);
        if (response.rtc === null && response.call.canRejoin) {
            response = await joinCall(response.call.publicId, cameraEnabled.value, microphoneEnabled.value);
        }
        call.value = response.call;
        if (response.rtc === null) {
            stopPreview();
            resultMessage.value = t(`calls.errors.${response.call.status}`);
            stage.value = 'result';
            return;
        }
        await connectMedia(response);
    } catch {
        stage.value = 'precall';
        error.value = t('calls.errors.connect');
    } finally {
        busy.value = false;
    }
}

async function connectMedia(response: CallJoinResponse): Promise<void> {
    if (response.rtc === null) throw new Error('RTC access was not returned.');
    stopPreview();
    const { CallMediaSession: MediaSession } = await import('../../Services/callMediaSession');
    const media = new MediaSession();
    media.onTrackSubscribed((element, identity, source) => {
        element.dataset.participant = identity;
        element.dataset.source = source;
        element.autoplay = true;
        if (element instanceof HTMLVideoElement) element.playsInline = true;
        remoteMedia.value?.appendChild(element);
    });
    await media.connect(response.rtc, { camera: cameraEnabled.value, microphone: microphoneEnabled.value });
    if (preferences.value.speakerDeviceId) {
        await media.switchDevice('audiooutput', preferences.value.speakerDeviceId).catch(() => undefined);
    }
    session.value = media;
    stage.value = 'connected';
}

async function toggleCamera(): Promise<void> {
    const enabled = !cameraEnabled.value;
    await session.value?.camera(enabled);
    cameraEnabled.value = enabled;
    await syncMedia();
}

async function toggleMicrophone(): Promise<void> {
    const enabled = !microphoneEnabled.value;
    await session.value?.microphone(enabled);
    microphoneEnabled.value = enabled;
    await syncMedia();
}

async function toggleScreenShare(): Promise<void> {
    const enabled = !screenSharing.value;

    try {
        await updateScreenShare(requiredCall().publicId, enabled);
        await session.value?.screenShare(enabled);
        screenSharing.value = enabled;
    } catch {
        error.value = t('calls.errors.screen_share_busy');
    }
}

async function switchDevice(kind: MediaDeviceKind, deviceId: string | number | null): Promise<void> {
    if (deviceId === null) return;
    const value = String(deviceId);
    if (stage.value === 'precall' && (kind === 'videoinput' || kind === 'audioinput')) {
        stopPreview();
        previewStream.value = await preferredMediaStream().catch(() =>
            navigator.mediaDevices.getUserMedia({ audio: true, video: cameraEnabled.value }),
        );
        await nextTick();
        if (preview.value) preview.value.srcObject = previewStream.value;
    } else if (value !== '') {
        await session.value?.switchDevice(kind, value);
    }
    await persistPreferences();
}

async function updatePreviewCamera(value: boolean | string[]): Promise<void> {
    if (typeof value !== 'boolean') return;
    const enabled = value;
    cameraEnabled.value = enabled;
    if (stage.value !== 'precall') return;
    stopPreview();

    try {
        previewStream.value = await preferredMediaStream();
        await nextTick();
        if (preview.value) preview.value.srcObject = previewStream.value;
    } catch {
        error.value = t('calls.errors.media_permission');
    }
}

async function leave(): Promise<void> {
    busy.value = true;
    try {
        const updated = await leaveCall(requiredCall().publicId);
        call.value = updated.status === 'ringing' || updated.status === 'active' ? updated : null;
        session.value?.disconnect();
        session.value = null;
        stage.value = 'idle';
        screenSharing.value = false;
    } finally {
        busy.value = false;
    }
}

async function decline(): Promise<void> {
    busy.value = true;
    try {
        await declineCall(requiredCall().publicId);
        call.value = null;
        stage.value = 'idle';
    } finally {
        busy.value = false;
    }
}

async function syncMedia(): Promise<void> {
    await updateCallMedia(requiredCall().publicId, cameraEnabled.value, microphoneEnabled.value);
}

async function persistPreferences(): Promise<void> {
    preferences.value = await saveCallPreferences(preferences.value);
}

function stopPreview(): void {
    previewStream.value?.getTracks().forEach((track) => track.stop());
    previewStream.value = null;
    if (preview.value) preview.value.srcObject = null;
}

function closeDialog(): void {
    if (stage.value === 'connected' || call.value?.incoming) return;
    stopPreview();
    stage.value = 'idle';
    startConversation.value = null;
    resultMessage.value = null;
    if (call.value !== null && !call.value.status.match(/^(ringing|active)$/)) call.value = null;
}

function requiredCall(): CallSnapshot {
    if (call.value === null) throw new Error('Call is unavailable.');
    return call.value;
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

function isPrepareCallDetail(value: unknown): value is { conversationPublicId: string; cameraEnabled: boolean | null } {
    return (
        typeof value === 'object' &&
        value !== null &&
        'conversationPublicId' in value &&
        typeof value.conversationPublicId === 'string' &&
        'cameraEnabled' in value &&
        (typeof value.cameraEnabled === 'boolean' || value.cameraEnabled === null)
    );
}

function alertIncoming(value: CallSnapshot | null): void {
    if (value === null || (!value.incoming && !value.teamJoinStyle) || lastAlertedCall === value.publicId) return;
    lastAlertedCall = value.publicId;
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(value.teamJoinStyle ? t('calls.team_available.title') : t('calls.incoming.title'), {
            body: value.conversationLabel,
            tag: `atlas-call-${value.publicId}`,
        });
    }
}
</script>

<template>
    <aside
        v-if="teamBanner && call"
        class="fixed right-4 bottom-4 z-70 w-[min(26rem,calc(100vw-2rem))] rounded-lg border border-sky-200 bg-white p-4 shadow-xl dark:border-sky-900 dark:bg-zinc-950"
        role="status"
        data-testid="team-call-available"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200"
            >
                <IconPhone aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-zinc-950 dark:text-zinc-50">{{ t('calls.team_available.title') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ call.conversationLabel }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ t('calls.team_available.no_ring') }}</p>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            <FormButton tone="neutral" :icon="IconPhone" @click="prepareExisting(false)">{{ t('calls.actions.join_audio') }}</FormButton>
            <FormButton :icon="IconVideo" @click="prepareExisting(true)">{{ t('calls.actions.join_video') }}</FormButton>
        </div>
    </aside>
    <DialogPanel
        :open="dialogOpen"
        :title="title"
        :icon="stage === 'connected' ? IconPhone : IconVideo"
        size="3xl"
        :close-label="t('modal.close')"
        @update:open="closeDialog"
    >
        <div v-if="stage === 'precall' || stage === 'connecting'" class="space-y-4" data-testid="call-preflight">
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
                <FormCheckbox :model-value="cameraEnabled" :label="t('calls.pre_call.camera')" @update:model-value="updatePreviewCamera" />
                <FormCheckbox v-model="microphoneEnabled" :label="t('calls.pre_call.microphone')" />
                <FormCheckbox v-model="preferences.outgoingCameraEnabled" :label="t('calls.pre_call.remember_camera')" />
            </div>
        </div>

        <div v-else-if="stage === 'connected'" class="space-y-4" data-testid="active-call">
            <div v-if="call" class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ t(`calls.statuses.${call.status}`) }}</p>
                <ul class="flex flex-wrap gap-2" :aria-label="t('calls.participants')">
                    <li
                        v-for="participant in call.participants"
                        :key="participant.publicId"
                        class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                    >
                        {{ participant.name }} · {{ t(`calls.states.${participant.state}`) }}
                    </li>
                </ul>
            </div>
            <div
                ref="remoteMedia"
                class="grid min-h-64 gap-3 rounded-lg bg-zinc-950 p-3 sm:grid-cols-2"
                :aria-label="t('calls.remote_media')"
            />
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
            <div class="flex flex-wrap justify-center gap-2">
                <FormButton tone="neutral" :icon="cameraEnabled ? IconVideo : IconVideoOff" @click="toggleCamera">
                    {{ cameraEnabled ? t('calls.actions.camera_off') : t('calls.actions.camera_on') }}
                </FormButton>
                <FormButton tone="neutral" :icon="microphoneEnabled ? IconMicrophone : IconMicrophoneOff" @click="toggleMicrophone">
                    {{ microphoneEnabled ? t('calls.actions.microphone_off') : t('calls.actions.microphone_on') }}
                </FormButton>
                <FormButton tone="neutral" :icon="screenSharing ? IconDeviceDesktopOff : IconDeviceDesktopShare" @click="toggleScreenShare">
                    {{ screenSharing ? t('calls.actions.screen_share_off') : t('calls.actions.screen_share_on') }}
                </FormButton>
                <FormButton tone="danger" :icon="IconPhoneOff" :loading="busy" @click="leave">{{ t('calls.actions.leave') }}</FormButton>
            </div>
        </div>

        <p
            v-else-if="stage === 'result' && resultMessage"
            class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-100"
            role="status"
        >
            {{ resultMessage }}
        </p>

        <div v-else-if="call" class="space-y-3">
            <p class="text-base font-medium text-zinc-950 dark:text-zinc-50">{{ call.conversationLabel }}</p>
            <p>{{ call.teamJoinStyle ? t('calls.team_available.body') : t('calls.incoming.body', { caller: call.startedByName }) }}</p>
            <p v-if="call.teamJoinStyle" class="text-xs text-zinc-500 dark:text-zinc-400">{{ t('calls.team_available.no_ring') }}</p>
        </div>

        <template #actions>
            <template v-if="stage === 'precall' || stage === 'connecting'">
                <FormButton tone="neutral" :disabled="busy" @click="closeDialog">{{ t('actions.cancel') }}</FormButton>
                <FormButton :icon="IconPhone" :loading="busy" @click="connect">{{ t('calls.actions.join') }}</FormButton>
            </template>
            <template v-else-if="stage === 'idle' && call">
                <FormButton v-if="call.incoming" tone="danger" :icon="IconPhoneOff" :loading="busy" @click="decline">
                    {{ t('calls.actions.decline') }}
                </FormButton>
                <FormButton v-if="canPrepare" tone="neutral" :icon="IconPhone" :loading="busy" @click="prepareExisting(false)">
                    {{ call.incoming ? t('calls.actions.answer_audio') : t('calls.actions.join_audio') }}
                </FormButton>
                <FormButton v-if="canPrepare" :icon="IconVideo" :loading="busy" @click="prepareExisting(true)">
                    {{ call.incoming ? t('calls.actions.answer_video') : t('calls.actions.join_video') }}
                </FormButton>
            </template>
            <FormButton v-else-if="stage === 'result'" tone="neutral" @click="closeDialog">{{ t('modal.close') }}</FormButton>
        </template>
    </DialogPanel>
</template>
